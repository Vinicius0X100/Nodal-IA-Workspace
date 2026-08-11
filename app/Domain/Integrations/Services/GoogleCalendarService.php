<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Integrations\Exceptions\GoogleCalendarException;
use App\Domain\Integrations\Exceptions\GoogleReauthRequiredException;
use App\Domain\Integrations\Exceptions\IntegrationUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * GoogleCalendarService — READ-ONLY v1
 *
 * Responsabilidades:
 *  - Obter access_token válido via GoogleTokenService (nunca diretamente).
 *  - Montar e executar chamadas à Google Calendar API v3.
 *  - Normalizar a resposta em formato seguro e tipado para a AI API.
 *  - Lidar com erros do provider de forma controlada.
 *  - Nunca expor tokens, secrets nem payloads brutos.
 *
 * Decisão de Timezone:
 *  - A timezone é resolvida na seguinte ordem de prioridade:
 *    1. Parâmetro `time_zone` explícito na requisição.
 *    2. Timezone da organização (organization->timezone), se existir.
 *    3. Fallback: 'America/Sao_Paulo' (documentado aqui, nunca espalhado).
 *  - Essa resolução é feita em resolveTimezone() e reutilizada em todo o service.
 */
class GoogleCalendarService
{
    private const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const DEFAULT_LIMIT     = 20;
    private const MAX_LIMIT         = 100;
    private const DEFAULT_TIMEZONE  = 'America/Sao_Paulo';

    public function __construct(
        private GoogleTokenService $tokenService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lista eventos do Google Calendar para a organização.
     *
     * @param Organization $organization
     * @param Integration  $integration  Deve ser o Google Workspace da organização.
     * @param array        $filters      Filtros validados vindos do controller.
     * @param int|null     $actingUserId Para auditoria.
     * @param string|null  $conversationUuid Para auditoria.
     * @return array
     * @throws GoogleCalendarException
     * @throws GoogleReauthRequiredException
     * @throws IntegrationUnavailableException
     */
    public function listEvents(
        Organization $organization,
        Integration $integration,
        array $filters = [],
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        $calendarId = $filters['calendar_id'] ?? 'primary';
        $timeZone   = $this->resolveTimezone($organization, $filters['time_zone'] ?? null);
        $start      = $filters['start'] ?? Carbon::now($timeZone)->toRfc3339String();
        $end        = $filters['end']   ?? Carbon::now($timeZone)->addDays(7)->toRfc3339String();
        $limit      = min((int) ($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);
        $query      = $filters['query'] ?? null;

        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $start, $end, $limit, $query, $timeZone) {
                    $params = [
                        'timeMin'      => $start,
                        'timeMax'      => $end,
                        'singleEvents' => 'true',
                        'orderBy'      => 'startTime',
                        'maxResults'   => $limit,
                        'timeZone'     => $timeZone,
                    ];

                    if ($query) {
                        $params['q'] = $query;
                    }

                    return Http::withToken($accessToken)
                        ->get(self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events', $params);
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.readonly']
            );

            return $this->handleResponse($response, $calendarId, $timeZone, $start, $end, $organization, $actingUserId, $conversationUuid, $limit);

        } catch (GoogleCalendarException|GoogleReauthRequiredException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Falha inesperada ao listar eventos.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'message'         => $e->getMessage(),
            ]);

            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'Não foi possível consultar o Google Calendar no momento.');
        }
    }

    /**
     * Consulta disponibilidade (Free/Busy) do calendário.
     *
     * @param Organization $organization
     * @param Integration  $integration
     * @param array        $filters      [start, end, calendar_id, slot_duration_minutes]
     * @param int|null     $actingUserId
     * @param string|null  $conversationUuid
     * @return array
     */
    public function getFreeBusy(
        Organization $organization,
        Integration $integration,
        array $filters,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        $calendarId          = $filters['calendar_id'] ?? 'primary';
        $timeZone            = $this->resolveTimezone($organization, null);
        $start               = $filters['start']; // Já validado como RFC3339
        $end                 = $filters['end'];   // Já validado como RFC3339
        $slotDurationMinutes = (int) ($filters['slot_duration_minutes'] ?? 30);

        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $start, $end, $timeZone) {
                    return Http::withToken($accessToken)
                        ->post(self::CALENDAR_API_BASE . '/freeBusy', [
                            'timeMin'  => $start,
                            'timeMax'  => $end,
                            'timeZone' => $timeZone,
                            'items'    => [['id' => $calendarId]],
                        ]);
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.readonly']
            );

            return $this->handleFreeBusyResponse($response, $calendarId, $timeZone, $start, $end, $slotDurationMinutes, $organization, $actingUserId, $conversationUuid);

        } catch (GoogleCalendarException|GoogleReauthRequiredException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Falha inesperada ao consultar FreeBusy.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'message'         => $e->getMessage(),
            ]);
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'Não foi possível consultar a disponibilidade no momento.');
        }
    }

    /**
     * Cria um evento no Google Calendar.
     *
     * @param Organization $organization
     * @param Integration  $integration
     * @param array        $eventData    Dados do evento a ser criado
     * @param int|null     $actingUserId
     * @param string|null  $conversationUuid
     * @param \App\Domain\Identities\Models\ExternalIdentity|null $identity
     * @return array
     */
    public function createEvent(
        Organization $organization,
        Integration $integration,
        array $eventData,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        $calendarId = 'primary'; // DWD operando sempre no primário por design desta feature
        $timeZone   = $this->resolveTimezone($organization, $eventData['time_zone'] ?? null);

        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $eventData, $timeZone) {
                    $payload = [
                        'summary' => $eventData['summary'],
                        'start'   => [
                            'dateTime' => $eventData['start'],
                            'timeZone' => $timeZone,
                        ],
                        'end'     => [
                            'dateTime' => $eventData['end'],
                            'timeZone' => $timeZone,
                        ],
                    ];

                    if (!empty($eventData['description'])) {
                        $payload['description'] = $eventData['description'];
                    }

                    if (!empty($eventData['location'])) {
                        $payload['location'] = $eventData['location'];
                    }

                    if (!empty($eventData['attendees'])) {
                        $payload['attendees'] = $eventData['attendees'];
                    }

                    if (!empty($eventData['create_meeting'])) {
                        $payload['conferenceData'] = [
                            'createRequest' => [
                                'requestId' => 'nodal-' . \Illuminate\Support\Str::uuid()->toString(),
                                'conferenceSolutionKey' => [
                                    'type' => 'hangoutsMeet',
                                ],
                            ],
                        ];
                    }

                    $queryParams = [];
                    if (!empty($eventData['attendees'])) {
                        $queryParams['sendUpdates'] = 'all'; // Dispara convite por padrão somente se houver convidados
                    }
                    
                    if (!empty($eventData['create_meeting'])) {
                        $queryParams['conferenceDataVersion'] = 1;
                    }

                    return Http::withToken($accessToken)
                        ->post(self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events?' . http_build_query($queryParams), $payload);
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.events'] // Escopo de escrita necessário
            );

            return $this->handleCreateEventResponse($response, $calendarId, $timeZone, $organization, $actingUserId, $conversationUuid, $identity);

        } catch (GoogleCalendarException|GoogleReauthRequiredException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Falha inesperada ao criar evento.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'message'         => $e->getMessage(),
            ]);

            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'Não foi possível criar o evento no momento.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trata a resposta de FreeBusy e calcula janelas livres.
     */
    private function handleFreeBusyResponse(
        \Illuminate\Http\Client\Response $response,
        string $calendarId,
        string $timeZone,
        string $start,
        string $end,
        int $slotDurationMinutes,
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid
    ): array {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'GOOGLE_REAUTH_REQUIRED', 'ai_calendar_freebusy_read');
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'ACCESS_DENIED', 'ai_calendar_freebusy_read');
            throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao Google Calendar.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'CALENDAR_NOT_FOUND', 'ai_calendar_freebusy_read');
            throw new GoogleCalendarException('CALENDAR_NOT_FOUND', "Calendário '{$calendarId}' não encontrado.");
        }

        if (!$response->successful()) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'GOOGLE_CALENDAR_UNAVAILABLE', 'ai_calendar_freebusy_read');
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'O Google Calendar retornou um erro inesperado.');
        }

        $body = $response->json();
        
        // Em freeBusy, se o calendarId não for reconhecido, o Google às vezes retorna na chave de erro.
        if (isset($body['calendars'][$calendarId]['errors'])) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'CALENDAR_NOT_FOUND', 'ai_calendar_freebusy_read');
            throw new GoogleCalendarException('CALENDAR_NOT_FOUND', "Erro ao acessar a agenda '{$calendarId}'.");
        }

        $busyIntervalsRaw = $body['calendars'][$calendarId]['busy'] ?? [];
        
        // 1. Parse, Sort, and Merge Busy Intervals
        $busyIntervals = $this->mergeBusyIntervals($busyIntervalsRaw);

        // 2. Calculate Free Intervals
        $freeIntervals = $this->calculateFreeIntervals($start, $end, $busyIntervals, $slotDurationMinutes);

        // 3. Flags
        $startCarbon = Carbon::parse($start);
        $endCarbon   = Carbon::parse($end);
        $totalDurationMinutes = $startCarbon->diffInMinutes($endCarbon);

        $isFullyFree = empty($busyIntervals);
        
        // É fully busy se não sobrou nenhuma janela livre de pelo menos $slotDurationMinutes (ou nenhuma em absoluto se passarmos 0)
        // Para ser perfeitamente "fully busy", todos os free_intervals devem estar vazios
        $isFullyBusy = empty($freeIntervals);

        $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, count($busyIntervals), true, null, 'ai_calendar_freebusy_read');

        return [
            'calendar' => [
                'id'        => $calendarId,
                'time_zone' => $timeZone,
            ],
            'range' => [
                'start' => $start,
                'end'   => $end,
            ],
            'busy' => array_map(fn($b) => [
                'start' => $b['start']->toRfc3339String(),
                'end'   => $b['end']->toRfc3339String()
            ], $busyIntervals),
            'free' => array_map(fn($f) => [
                'start' => $f['start']->toRfc3339String(),
                'end'   => $f['end']->toRfc3339String(),
                'duration_minutes' => $f['duration_minutes']
            ], $freeIntervals),
            'is_fully_free' => $isFullyFree,
            'is_fully_busy' => $isFullyBusy,
        ];
    }

    /**
     * Ordena e une (merge) intervalos ocupados que se sobrepõem ou se tocam.
     */
    private function mergeBusyIntervals(array $rawBusy): array
    {
        if (empty($rawBusy)) return [];

        $intervals = array_map(function($b) {
            return [
                'start' => Carbon::parse($b['start']),
                'end'   => Carbon::parse($b['end']),
            ];
        }, $rawBusy);

        usort($intervals, fn($a, $b) => $a['start']->lt($b['start']) ? -1 : 1);

        $merged = [$intervals[0]];
        for ($i = 1; $i < count($intervals); $i++) {
            $last = &$merged[count($merged) - 1];
            $current = $intervals[$i];

            // Se o atual começa antes ou no mesmo instante que o anterior termina
            if ($current['start']->lte($last['end'])) {
                // Estende o final se necessário
                if ($current['end']->gt($last['end'])) {
                    $last['end'] = $current['end'];
                }
            } else {
                $merged[] = $current;
            }
        }

        return $merged;
    }

    /**
     * Subtrai os intervalos ocupados do período total e descarta janelas menores que o slot mínimo.
     */
    private function calculateFreeIntervals(string $start, string $end, array $mergedBusy, int $slotDurationMinutes): array
    {
        $globalStart = Carbon::parse($start);
        $globalEnd   = Carbon::parse($end);
        
        $free = [];
        $currentStart = $globalStart->copy();

        foreach ($mergedBusy as $busy) {
            // Se o bloco busy está totalmente antes do range de busca (não deveria, a API já filtra, mas por segurança)
            if ($busy['end']->lte($currentStart)) {
                continue;
            }

            // Se houver espaço entre o começo atual e o começo do bloco busy
            if ($busy['start']->gt($currentStart)) {
                // Mas o busy não pode ultrapassar o globalEnd
                $freeEnd = $busy['start']->copy();
                if ($freeEnd->gt($globalEnd)) {
                    $freeEnd = $globalEnd->copy();
                }

                $diff = $currentStart->diffInMinutes($freeEnd);
                if ($diff >= $slotDurationMinutes) {
                    $free[] = [
                        'start' => $currentStart->copy(),
                        'end'   => $freeEnd,
                        'duration_minutes' => $diff,
                    ];
                }
            }

            // Avança o começo atual para o fim do bloco busy
            if ($busy['end']->gt($currentStart)) {
                $currentStart = $busy['end']->copy();
            }
        }

        // Checa do fim do último bloco busy até o globalEnd
        if ($currentStart->lt($globalEnd)) {
            $diff = $currentStart->diffInMinutes($globalEnd);
            if ($diff >= $slotDurationMinutes) {
                $free[] = [
                    'start' => $currentStart->copy(),
                    'end'   => $globalEnd->copy(),
                    'duration_minutes' => $diff,
                ];
            }
        }

        return $free;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal API Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trata a resposta da API do Google e normaliza para o formato Nodal.
     */
    private function handleResponse(
        \Illuminate\Http\Client\Response $response,
        string $calendarId,
        string $timeZone,
        string $start,
        string $end,
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        int $limit
    ): array {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'GOOGLE_REAUTH_REQUIRED');
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'ACCESS_DENIED');
            throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao Google Calendar. Verifique os escopos da integração.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'CALENDAR_NOT_FOUND');
            throw new GoogleCalendarException('CALENDAR_NOT_FOUND', "Calendário '{$calendarId}' não encontrado na conta Google da organização.");
        }

        if (!$response->successful()) {
            $body = $response->json();
            $reason = $body['error']['message'] ?? ('HTTP ' . $response->status());

            Log::warning('[GoogleCalendarService] Resposta inesperada do Google Calendar.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'http_status'     => $response->status(),
                'reason'          => $reason,
            ]);

            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, 0, false, 'GOOGLE_CALENDAR_UNAVAILABLE');
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'O Google Calendar retornou um erro inesperado.');
        }

        $body        = $response->json();
        $rawEvents   = $body['items'] ?? [];
        $calTimezone = $body['timeZone'] ?? $timeZone;
        
        $normalized = [];
        $startCarbon = Carbon::parse($start);
        $endCarbon   = Carbon::parse($end);

        foreach ($rawEvents as $e) {
            $event = $this->normalizeEvent($e);
            $eventStartRaw = $event['start'] ?? null;
            
            if (!$eventStartRaw) {
                continue;
            }

            // Para eventos all-day, eventStartRaw é um formato de data 'Y-m-d' que será assumido no timezone do calendário
            $eventStartCarbon = Carbon::parse($eventStartRaw, $calTimezone);

            // O evento pertence ao intervalo se: start >= timeMin AND start < timeMax
            if ($eventStartCarbon->gte($startCarbon) && $eventStartCarbon->lt($endCarbon)) {
                $normalized[] = $event;
            }
        }

        $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $start, $end, count($normalized), true, null);

        return [
            'calendar' => [
                'id'        => $calendarId,
                'time_zone' => $calTimezone,
            ],
            'range'    => [
                'start' => $start,
                'end'   => $end,
            ],
            'events'   => $normalized,
            'total'    => count($normalized),
        ];
    }

    /**
     * Trata a resposta de Criação de Evento.
     */
    private function handleCreateEventResponse(
        \Illuminate\Http\Client\Response $response,
        string $calendarId,
        string $timeZone,
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity
    ): array {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_REAUTH_REQUIRED', 'ai_calendar_event_create');
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'ACCESS_DENIED', 'ai_calendar_event_create');
            throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao Google Calendar. Verifique os escopos da integração.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'CALENDAR_NOT_FOUND', 'ai_calendar_event_create');
            throw new GoogleCalendarException('CALENDAR_NOT_FOUND', "Calendário '{$calendarId}' não encontrado na conta Google da organização.");
        }

        if (!$response->successful()) {
            $body = $response->json();
            $reason = $body['error']['message'] ?? ('HTTP ' . $response->status());
            
            // LOG TEMPORÁRIO PARA DEBUG DWD
            Log::warning('[DEBUG_DWD] Falha na criação do evento.', [
                'http_status' => $response->status(),
                'error.message' => $body['error']['message'] ?? null,
                'error.errors' => array_map(function($err) {
                    return [
                        'reason' => $err['reason'] ?? null,
                        'domain' => $err['domain'] ?? null,
                    ];
                }, $body['error']['errors'] ?? []),
                'response_body_sanitized' => $body,
                'subject_email' => $identity ? $identity->primary_email : null,
                'calendarId' => $calendarId,
            ]);

            Log::warning('[GoogleCalendarService] Resposta inesperada ao criar evento no Google Calendar.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'http_status'     => $response->status(),
                'reason'          => $reason,
            ]);

            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_CALENDAR_UNAVAILABLE', 'ai_calendar_event_create');
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'O Google Calendar retornou um erro inesperado.');
        }

        $event = $this->normalizeEvent($response->json());
        
        $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $event['start'] ?? '', $event['end'] ?? '', 1, true, null, 'ai_calendar_event_create');

        return [
            'event' => array_merge($event, [
                'calendar' => [
                    'owner_user_uuid' => $identity ? $identity->user->uuid : null,
                    'provider' => 'google_workspace'
                ]
            ]),
        ];
    }

    /**
     * Normaliza um evento bruto do Google para o formato Nodal AI.
     * Nunca retorna campos internos, tokens ou payloads brutos.
     */
    private function normalizeEvent(array $event): array
    {
        // Determina se é all-day
        $startRaw = $event['start']['dateTime'] ?? null;
        $endRaw   = $event['end']['dateTime']   ?? null;
        $allDay   = false;

        if (!$startRaw) {
            // all-day: campo "date" ao invés de "dateTime"
            $startRaw = $event['start']['date'] ?? null;
            $endRaw   = $event['end']['date']   ?? null;
            $allDay   = true;
        }

        // Organizer
        $organizer = null;
        if (!empty($event['organizer'])) {
            $organizer = [
                'name'  => $event['organizer']['displayName'] ?? null,
                'email' => $event['organizer']['email']       ?? null,
            ];
        }

        // Attendees
        $attendees = [];
        foreach ($event['attendees'] ?? [] as $a) {
            $attendees[] = [
                'name'            => $a['displayName']   ?? null,
                'email'           => $a['email']         ?? null,
                'response_status' => $a['responseStatus'] ?? null,
            ];
        }

        // Meeting link (Google Meet, Zoom, Teams via conferenceData)
        $meetingUrl = null;
        if (!empty($event['conferenceData']['entryPoints'])) {
            foreach ($event['conferenceData']['entryPoints'] as $entry) {
                if (($entry['entryPointType'] ?? '') === 'video') {
                    $meetingUrl = $entry['uri'] ?? null;
                    break;
                }
            }
        }
        // Fallback: location que começa com https://
        if (!$meetingUrl && !empty($event['location']) && str_starts_with($event['location'], 'https://')) {
            $meetingUrl = $event['location'];
        }

        return [
            'external_id' => $event['id']          ?? null,
            'title'       => $event['summary']      ?? null,
            'description' => isset($event['description']) ? mb_strimwidth($event['description'], 0, 500, '...') : null,
            'location'    => $event['location']     ?? null,
            'start'       => $startRaw,
            'end'         => $endRaw,
            'all_day'     => $allDay,
            'status'      => $event['status']       ?? null,
            'recurrence_id' => $event['recurringEventId'] ?? null,
            'organizer'   => $organizer,
            'attendees'   => $attendees,
            'meeting'     => $meetingUrl ? ['url' => $meetingUrl] : null,
        ];
    }

    /**
     * Resolve timezone na ordem de prioridade documentada:
     * 1. Explícito na requisição
     * 2. Organização (se tiver campo timezone)
     * 3. Fallback: America/Sao_Paulo
     */
    private function resolveTimezone(Organization $organization, ?string $requested): string
    {
        if ($requested && $this->isValidTimezone($requested)) {
            return $requested;
        }

        if (!empty($organization->timezone) && $this->isValidTimezone($organization->timezone)) {
            return $organization->timezone;
        }

        return self::DEFAULT_TIMEZONE;
    }

    private function isValidTimezone(string $tz): bool
    {
        try {
            new \DateTimeZone($tz);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Registra auditoria segura — nunca loga tokens, conteúdo de descrições completo ou IDs internos desnecessários.
     */
    private function audit(
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        string $calendarId,
        string $start,
        string $end,
        int $eventCount,
        bool $allowed,
        ?string $errorCode,
        string $action = 'ai_calendar_events_read'
    ): void {
        try {
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id'         => $actingUserId,
                'action'          => $action,
                'entity_type'     => Organization::class,
                'entity_id'       => $organization->id,
                'metadata'        => array_filter([
                    'provider'          => 'google_workspace',
                    'calendar_id'       => $calendarId,
                    'start'             => $start,
                    'end'               => $end,
                    'event_count'       => $eventCount,
                    'allowed'           => $allowed,
                    'error_code'        => $errorCode,
                    'conversation_uuid' => $conversationUuid,
                ], fn($v) => !is_null($v)),
            ]);
        } catch (\Exception $e) {
            Log::warning('[GoogleCalendarService] Falha ao registrar auditoria.', ['message' => $e->getMessage()]);
        }
    }
}
