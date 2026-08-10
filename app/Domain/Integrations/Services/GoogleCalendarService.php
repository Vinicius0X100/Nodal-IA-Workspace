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
        ?string $conversationUuid = null
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
                }
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

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
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
        ?string $errorCode
    ): void {
        try {
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id'         => $actingUserId,
                'action'          => 'ai_calendar_events_read',
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
