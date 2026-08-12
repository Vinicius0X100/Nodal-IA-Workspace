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
    /**
     * Cria um evento no Google Calendar.
     *
     * @param array  $attendeesMeta [{user_uuid, name, email}] — para resposta normalizada
     * @param bool   $createMeeting — se true, cria Google Meet via conferenceData
     * @param string|null $meetRequestId — requestId idempotente derivado pelo controller
     */
    public function createEvent(
        Organization $organization,
        Integration $integration,
        array $eventData,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null,
        array $attendeesMeta = [],
        bool $createMeeting = false,
        ?string $meetRequestId = null
    ): array {
        $calendarId = 'primary'; // DWD operando sempre no primário por design desta feature
        $timeZone   = $this->resolveTimezone($organization, $eventData['time_zone'] ?? null);

        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $eventData, $timeZone, $createMeeting, $meetRequestId) {
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

                    // attendees já foram resolvidos pelo controller (user_uuid → primary_email)
                    if (!empty($eventData['attendees'])) {
                        $payload['attendees'] = $eventData['attendees'];
                    }

                    // Google Meet — requestId idempotente vindo do controller
                    if ($createMeeting) {
                        $payload['conferenceData'] = [
                            'createRequest' => [
                                'requestId'            => $meetRequestId ?? ('nodal-' . \Illuminate\Support\Str::uuid()->toString()),
                                'conferenceSolutionKey' => [
                                    'type' => 'hangoutsMeet',
                                ],
                            ],
                        ];
                    }

                    $queryParams = [];
                    // sendUpdates apenas quando há convidados — evita erro 400 com lista vazia
                    if (!empty($eventData['attendees'])) {
                        $queryParams['sendUpdates'] = 'all';
                    }
                    // conferenceDataVersion=1 obrigatório para ativar Meet
                    if ($createMeeting) {
                        $queryParams['conferenceDataVersion'] = 1;
                    }

                    return Http::withToken($accessToken)
                        ->post(self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events?' . http_build_query($queryParams), $payload);
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.events']
            );

            return $this->handleCreateEventResponse(
                $response,
                $calendarId,
                $timeZone,
                $organization,
                $actingUserId,
                $conversationUuid,
                $identity,
                $attendeesMeta,
                $createMeeting
            );

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
    // Public API — Write v2: Update
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Atualiza um evento existente no Google Calendar (estratégia GET→PUT).
     *
     * Fluxo:
     *  1. GET do evento atual (obtém estado real + ETag)
     *  2. Aplica somente os campos presentes em $changes sobre o estado atual
     *  3. PUT completo com If-Match: {etag} para proteção contra concorrência
     *
     * Regra de duração: se apenas start mudar (end ausente), preserva a duração original.
     * Attendees: substitui internos, preserva externos desconhecidos.
     * Meet: criado/removido somente por sinalização explícita; ausência = preservar.
     *
     * @param array $changes     Campos validados a alterar (somente presentes no payload)
     * @param array $attendeesMeta [{user_uuid, name, email}] — para resposta normalizada
     * @param bool  $createMeeting — adicionar Meet se ausente
     * @param bool  $removeMeeting — remover conferenceData explicitamente
     * @param string|null $meetRequestId — requestId idempotente derivado pelo controller
     */
    public function updateEvent(
        Organization $organization,
        Integration $integration,
        string $googleEventId,
        array $changes,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null,
        array $attendeesMeta = [],
        bool $createMeeting = false,
        bool $removeMeeting = false,
        ?string $meetRequestId = null,
        bool $checkConflicts = false
    ): array {
        $calendarId = 'primary';

        try {
            // ── 1. GET do evento atual + ETag ────────────────────────────────
            $getResponse = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $googleEventId) {
                    return Http::withToken($accessToken)
                        ->get(self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($googleEventId));
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.events']
            );

            if ($getResponse->status() === 404) {
                $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'EVENT_NOT_FOUND', 'ai_calendar_event_update');
                throw new GoogleCalendarException('EVENT_NOT_FOUND', 'O evento não foi encontrado no calendário.');
            }
            if ($getResponse->status() === 403) {
                $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'ACCESS_DENIED', 'ai_calendar_event_update');
                throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao evento. Verifique os escopos da integração.');
            }
            if (!$getResponse->successful()) {
                throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'Não foi possível obter o evento atual.');
            }

            $current  = $getResponse->json();
            $etag     = $getResponse->header('ETag') ?: ($current['etag'] ?? null);
            $timeZone = $this->resolveTimezone($organization, $changes['time_zone'] ?? null);

            // ── 2. Aplicar changes sobre o estado atual ───────────────────────
            $payload = $current; // base = estado real atual

            $changedFields = [];

            if (array_key_exists('title', $changes)) {
                $payload['summary'] = $changes['title'];
                $changedFields[] = 'title';
            }

            // description: null = remover (string vazia)
            if (array_key_exists('description', $changes)) {
                $payload['description'] = $changes['description'] ?? '';
                $changedFields[] = 'description';
            }

            // location: null = remover
            if (array_key_exists('location', $changes)) {
                if ($changes['location'] === null) {
                    unset($payload['location']);
                } else {
                    $payload['location'] = $changes['location'];
                }
                $changedFields[] = 'location';
            }

            // Datas — regra de duração preservada
            $startChanged = array_key_exists('start', $changes);
            $endChanged   = array_key_exists('end', $changes);

            if ($startChanged || $endChanged) {
                $currentStartRaw = $current['start']['dateTime'] ?? $current['start']['date'] ?? null;
                $currentEndRaw   = $current['end']['dateTime']   ?? $current['end']['date']   ?? null;

                if ($startChanged && !$endChanged && $currentStartRaw && $currentEndRaw) {
                    // Preservar duração original
                    $originalDuration = Carbon::parse($currentEndRaw)->diffInSeconds(Carbon::parse($currentStartRaw));
                    $newStart         = Carbon::parse($changes['start']);
                    $newEnd           = $newStart->copy()->addSeconds($originalDuration);
                    $payload['start'] = ['dateTime' => $newStart->toRfc3339String(), 'timeZone' => $timeZone];
                    $payload['end']   = ['dateTime' => $newEnd->toRfc3339String(),   'timeZone' => $timeZone];
                } else {
                    if ($startChanged) {
                        $payload['start'] = ['dateTime' => $changes['start'], 'timeZone' => $timeZone];
                    }
                    if ($endChanged) {
                        $payload['end'] = ['dateTime' => $changes['end'], 'timeZone' => $timeZone];
                    }
                }
                $changedFields[] = 'start';
                if ($endChanged || (!$endChanged && $startChanged)) {
                    $changedFields[] = 'end';
                }
                $changedFields = array_unique($changedFields);
                
                if ($checkConflicts) {
                    $computedStart = $payload['start']['dateTime'] ?? $payload['start']['date'] ?? $current['start']['dateTime'] ?? $current['start']['date'] ?? null;
                    $computedEnd   = $payload['end']['dateTime']   ?? $payload['end']['date']   ?? $current['end']['dateTime']   ?? $current['end']['date']   ?? null;
                    
                    if ($computedStart && $computedEnd) {
                        $listResponse = $this->tokenService->executeWithRetry(
                            $integration,
                            function (string $accessToken) use ($calendarId, $computedStart, $computedEnd) {
                                return Http::withToken($accessToken)
                                    ->get(self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events', [
                                        'timeMin' => Carbon::parse($computedStart)->toRfc3339String(),
                                        'timeMax' => Carbon::parse($computedEnd)->toRfc3339String(),
                                        'singleEvents' => 'true',
                                    ]);
                            },
                            $identity,
                            ['https://www.googleapis.com/auth/calendar.events']
                        );
                        
                        if ($listResponse->successful()) {
                            $items = $listResponse->json('items') ?? [];
                            $conflictItems = array_filter($items, function ($item) use ($googleEventId) {
                                if (($item['id'] ?? null) === $googleEventId) return false;
                                if (($item['status'] ?? '') === 'cancelled') return false;
                                if (($item['transparency'] ?? '') === 'transparent') return false;
                                return true;
                            });
                            
                            if (!empty($conflictItems)) {
                                throw new GoogleCalendarException('EVENT_CONFLICT', 'O horário solicitado está ocupado.');
                            }
                        }
                    }
                }
            }

            // Attendees — lista final de internos, externos preservados
            if (array_key_exists('attendees', $changes)) {
                $existingAttendees = $current['attendees'] ?? [];

                // Emails internos conhecidos desta operação (para identificar "externos")
                $newInternalEmails = array_map(fn($m) => strtolower($m['email']), $attendeesMeta);

                // Emails internos anteriores que devemos substituir (lookup via ExternalIdentity)
                // Estratégia: qualquer attendee cujo email não está na lista de internos novos é tratado como externo
                // Para evitar apagar externos, mantemos todos os que NÃO eram internos conhecidos desta org
                $externalAttendees = [];
                if (!empty($existingAttendees)) {
                    // Busca todos os emails de ExternalIdentities desta organização para detectar quais são "internos"
                    $orgExternalEmails = \App\Domain\Identities\Models\ExternalIdentity::where('organization_id', $organization->id)
                        ->where('provider', 'google_workspace')
                        ->pluck('primary_email')
                        ->map(fn($e) => strtolower($e))
                        ->toArray();

                    foreach ($existingAttendees as $att) {
                        $email = strtolower($att['email'] ?? '');
                        // Preservar attendee se ele NÃO é interno desta organização
                        if (!in_array($email, $orgExternalEmails, true)) {
                            $externalAttendees[] = $att;
                        }
                    }
                }

                // Novos internos resolvidos + externos preservados
                $resolvedNew = array_map(fn($m) => ['email' => $m['email']], $attendeesMeta);
                $payload['attendees'] = array_merge($externalAttendees, $resolvedNew);
                $changedFields[] = 'attendees';
            }

            // Google Meet
            if ($removeMeeting) {
                // Remoção explícita — setar conferenceData como null no payload
                $payload['conferenceData'] = null;
                $changedFields[] = 'meeting';
            } elseif ($createMeeting) {
                $hasExistingMeet = !empty($current['conferenceData']['conferenceId']);
                if (!$hasExistingMeet) {
                    $payload['conferenceData'] = [
                        'createRequest' => [
                            'requestId'            => $meetRequestId ?? ('nodal-upd-' . \Illuminate\Support\Str::uuid()->toString()),
                            'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                        ],
                    ];
                    $changedFields[] = 'meeting';
                }
                // Se já tem Meet: idempotente — não altera
            }
            // Se nenhum dos dois: preservar conferenceData atual (já está em $payload)

            // ── 3. sendUpdates condicional ────────────────────────────────────
            $sendUpdatesFields = ['start', 'end', 'attendees', 'title', 'location'];
            $relevantChange    = !empty(array_intersect($changedFields, $sendUpdatesFields));
            $hasAttendees      = !empty($payload['attendees']);

            $queryParams = [];
            if ($relevantChange && $hasAttendees) {
                $queryParams['sendUpdates'] = 'all';
            }
            if ($createMeeting && !$removeMeeting) {
                $queryParams['conferenceDataVersion'] = 1;
            }

            // ── 4. PUT com If-Match (ETag) ────────────────────────────────────
            $putResponse = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($calendarId, $googleEventId, $payload, $queryParams, $etag) {
                    $request = Http::withToken($accessToken);
                    if ($etag) {
                        $request = $request->withHeaders(['If-Match' => $etag]);
                    }
                    return $request->put(
                        self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($googleEventId) . '?' . http_build_query($queryParams),
                        $payload
                    );
                },
                $identity,
                ['https://www.googleapis.com/auth/calendar.events']
            );

            return $this->handleUpdateEventResponse(
                $putResponse,
                $calendarId,
                $timeZone,
                $organization,
                $actingUserId,
                $conversationUuid,
                $identity,
                $attendeesMeta,
                $changedFields,
                $createMeeting,
                $removeMeeting
            );

        } catch (GoogleCalendarException|GoogleReauthRequiredException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] Falha inesperada ao atualizar evento.', [
                'organization_id' => $organization->id,
                'event_id'        => $googleEventId,
                'message'         => $e->getMessage(),
            ]);
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'Não foi possível atualizar o evento no momento.');
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
        ?\App\Domain\Identities\Models\ExternalIdentity $identity,
        array $attendeesMeta = [],
        bool $createMeeting = false
    ): array {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_REAUTH_REQUIRED', 'ai_calendar_event_create', [], false, false);
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'ACCESS_DENIED', 'ai_calendar_event_create', [], false, false);
            throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao Google Calendar. Verifique os escopos da integração.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'CALENDAR_NOT_FOUND', 'ai_calendar_event_create', [], false, false);
            throw new GoogleCalendarException('CALENDAR_NOT_FOUND', "Calendário '{$calendarId}' não encontrado na conta Google da organização.");
        }

        if (!$response->successful()) {
            $body   = $response->json();
            $reason = $body['error']['message'] ?? ('HTTP ' . $response->status());

            Log::warning('[GoogleCalendarService] Resposta inesperada ao criar evento no Google Calendar.', [
                'organization_id' => $organization->id,
                'calendar_id'     => $calendarId,
                'http_status'     => $response->status(),
                'reason'          => $reason,
                'subject_email'   => $identity ? $identity->primary_email : null,
            ]);

            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_CALENDAR_UNAVAILABLE', 'ai_calendar_event_create', [], false, false);
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'O Google Calendar retornou um erro inesperado.');
        }

        $rawBody = $response->json();
        $event   = $this->normalizeEventWithMeta($rawBody, $attendeesMeta);

        // Se create_meeting=true mas o Google não retornou conferenceData → falha explícita
        if ($createMeeting && empty($event['meeting'])) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, $event['start'] ?? '', $event['end'] ?? '', 1, false, 'GOOGLE_MEET_UNAVAILABLE', 'ai_calendar_event_create', array_column($attendeesMeta, 'user_uuid'), true, false);
            throw new GoogleCalendarException('GOOGLE_MEET_UNAVAILABLE', 'O Google Meet não pôde ser criado para este evento. Verifique os escopos e o Google Workspace Edition.');
        }

        $meetingCreated = !empty($event['meeting']);
        $attendeeUuids  = array_column($attendeesMeta, 'user_uuid');

        $this->audit(
            $organization, $actingUserId, $conversationUuid, $calendarId,
            $event['start'] ?? '', $event['end'] ?? '', 1, true, null,
            'ai_calendar_event_create', $attendeeUuids, $createMeeting, $meetingCreated
        );

        return [
            'event' => array_merge($event, [
                'calendar' => [
                    'owner_user_uuid' => $identity ? $identity->user->uuid : null,
                    'provider'        => 'google_workspace',
                ],
            ]),
        ];
    }

    /**
     * Normaliza um evento bruto do Google para o formato Nodal AI.
     * Nunca retorna campos internos, tokens ou payloads brutos.
     */
    /**
     * Trata a resposta do PUT de atualização de evento.
     * Normaliza erros, extrai changed_fields e registra auditoria.
     */
    private function handleUpdateEventResponse(
        \Illuminate\Http\Client\Response $response,
        string $calendarId,
        string $timeZone,
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity,
        array $attendeesMeta,
        array $changedFields,
        bool $createMeeting,
        bool $removeMeeting
    ): array {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_REAUTH_REQUIRED', 'ai_calendar_event_update');
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada.');
        }

        if ($response->status() === 412) {
            // ETag mismatch — evento foi modificado por outra pessoa entre GET e PUT
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'EVENT_CHANGED', 'ai_calendar_event_update');
            throw new GoogleCalendarException('EVENT_CHANGED', 'O evento foi alterado por outra pessoa enquanto você preparava a atualização. Busque o evento novamente e confirme as alterações.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'ACCESS_DENIED', 'ai_calendar_event_update');
            throw new GoogleCalendarException('ACCESS_DENIED', 'Acesso negado ao Google Calendar. Verifique os escopos da integração.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'EVENT_NOT_FOUND', 'ai_calendar_event_update');
            throw new GoogleCalendarException('EVENT_NOT_FOUND', 'O evento não foi encontrado no calendário.');
        }

        if (!$response->successful()) {
            $body   = $response->json();
            $reason = $body['error']['message'] ?? ('HTTP ' . $response->status());
            Log::warning('[GoogleCalendarService] Resposta inesperada ao atualizar evento.', [
                'organization_id' => $organization->id,
                'http_status'     => $response->status(),
                'reason'          => $reason,
                'subject_email'   => $identity ? $identity->primary_email : null,
            ]);
            $this->audit($organization, $actingUserId, $conversationUuid, $calendarId, '', '', 0, false, 'GOOGLE_CALENDAR_UNAVAILABLE', 'ai_calendar_event_update');
            throw new GoogleCalendarException('GOOGLE_CALENDAR_UNAVAILABLE', 'O Google Calendar retornou um erro inesperado.');
        }

        $rawBody = $response->json();
        $event   = $this->normalizeEventWithMeta($rawBody, $attendeesMeta);

        $meetingChanged = in_array('meeting', $changedFields, true);
        $attendeeUuids  = array_column($attendeesMeta, 'user_uuid');

        $this->audit(
            $organization, $actingUserId, $conversationUuid, $calendarId,
            $event['start'] ?? '', $event['end'] ?? '', 1, true, null,
            'ai_calendar_event_update', $attendeeUuids,
            $createMeeting && $meetingChanged,
            !empty($event['meeting'])
        );

        return [
            'event' => array_merge($event, [
                'calendar' => [
                    'owner_user_uuid' => $identity ? $identity->user->uuid : null,
                    'provider'        => 'google_workspace',
                ],
            ]),
            'changed_fields' => array_values(array_unique($changedFields)),
        ];
    }

    /**
     * Normaliza evento bruto — usado em listEvents (sem attendees_meta).
     */
    private function normalizeEvent(array $event): array
    {
        return $this->normalizeEventWithMeta($event, []);
    }

    /**
     * Normaliza um evento bruto do Google para o formato Nodal AI,
     * enriquecendo attendees com user_uuid e name quando disponível via attendees_meta.
     *
     * Nunca retorna tokens, IDs internos de BD ou payloads brutos.
     *
     * @param array $attendeesMeta [{user_uuid, name, email}] — indexado por email
     */
    private function normalizeEventWithMeta(array $event, array $attendeesMeta): array
    {
        // Determina se é all-day
        $startRaw = $event['start']['dateTime'] ?? null;
        $endRaw   = $event['end']['dateTime']   ?? null;
        $allDay   = false;

        if (!$startRaw) {
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

        // Índice de attendees_meta por email para lookup O(1)
        $metaByEmail = [];
        foreach ($attendeesMeta as $m) {
            if (!empty($m['email'])) {
                $metaByEmail[strtolower($m['email'])] = $m;
            }
        }

        // Attendees — enriquecidos com user_uuid e name quando disponível
        $attendees = [];
        foreach ($event['attendees'] ?? [] as $a) {
            $emailKey = strtolower($a['email'] ?? '');
            $meta     = $metaByEmail[$emailKey] ?? null;
            $attendees[] = [
                'user_uuid'       => $meta['user_uuid'] ?? null,
                'name'            => $meta['name'] ?? ($a['displayName'] ?? null),
                'email'           => $a['email']         ?? null,
                'response_status' => $a['responseStatus'] ?? 'needsAction',
            ];
        }

        // Meeting — Google Meet ou outra conferência via conferenceData
        $meeting = null;
        if (!empty($event['conferenceData'])) {
            $conferenceId = $event['conferenceData']['conferenceId'] ?? null;
            $meetingUrl   = null;
            foreach ($event['conferenceData']['entryPoints'] ?? [] as $entry) {
                if (($entry['entryPointType'] ?? '') === 'video') {
                    $meetingUrl = $entry['uri'] ?? null;
                    break;
                }
            }
            // Fallback: location que começa com https://
            if (!$meetingUrl && !empty($event['location']) && str_starts_with($event['location'], 'https://')) {
                $meetingUrl = $event['location'];
            }
            if ($meetingUrl) {
                $meeting = [
                    'provider'      => 'google_meet',
                    'url'           => $meetingUrl,
                    'conference_id' => $conferenceId,
                ];
            }
        }

        return [
            'external_id'   => $event['id']      ?? null,
            'title'         => $event['summary']  ?? null,
            'description'   => isset($event['description']) ? mb_strimwidth($event['description'], 0, 500, '...') : null,
            'location'      => $event['location'] ?? null,
            'start'         => $startRaw,
            'end'           => $endRaw,
            'all_day'       => $allDay,
            'status'        => $event['status']   ?? null,
            'recurrence_id' => $event['recurringEventId'] ?? null,
            'organizer'     => $organizer,
            'attendees'     => $attendees,
            'meeting'       => $meeting,
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
        string $action = 'ai_calendar_events_read',
        array $attendeeUserUuids = [],
        bool $createMeeting = false,
        bool $meetingCreated = false
    ): void {
        try {
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id'         => $actingUserId,
                'action'          => $action,
                'entity_type'     => Organization::class,
                'entity_id'       => $organization->id,
                'metadata'        => array_filter([
                    'provider'             => 'google_workspace',
                    'calendar_id'          => $calendarId,
                    'start'                => $start,
                    'end'                  => $end,
                    'event_count'          => $eventCount,
                    'allowed'              => $allowed,
                    'error_code'           => $errorCode,
                    'conversation_uuid'    => $conversationUuid,
                    // Campos de criação de evento
                    'attendee_count'       => $action === 'ai_calendar_event_create' ? count($attendeeUserUuids) : null,
                    'attendee_user_uuids'  => $action === 'ai_calendar_event_create' && !empty($attendeeUserUuids) ? $attendeeUserUuids : null,
                    'create_meeting'       => $action === 'ai_calendar_event_create' ? $createMeeting : null,
                    'meeting_created'      => $action === 'ai_calendar_event_create' ? $meetingCreated : null,
                    'conference_provider'  => ($action === 'ai_calendar_event_create' && $meetingCreated) ? 'google_meet' : null,
                ], fn($v) => !is_null($v)),
            ]);
        } catch (\Exception $e) {
            Log::warning('[GoogleCalendarService] Falha ao registrar auditoria.', ['message' => $e->getMessage()]);
        }
    }
}
