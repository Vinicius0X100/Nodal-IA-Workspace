<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\Integrations\Exceptions\GoogleCalendarException;
use App\Domain\Integrations\Exceptions\GoogleReauthRequiredException;
use App\Domain\Integrations\Exceptions\IntegrationUnavailableException;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleCalendarService;
use App\Domain\Permissions\Services\AuthorizationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AICalendarController — READ-ONLY v1
 *
 * Responsabilidades:
 *  - Validar a requisição.
 *  - Autorizar o usuário.
 *  - Resolver a integração Google Workspace da organização ativa.
 *  - Delegar ao GoogleCalendarService.
 *  - Retornar resposta normalizada.
 *
 * Nunca chama a Google API diretamente.
 * Nunca expõe tokens ao n8n ou à IA.
 */
class AICalendarController
{
    public function __construct(
        private GoogleCalendarService $calendarService,
        private AuthorizationService  $authorizationService,
    ) {}

    /**
     * GET /api/ai/calendar/events
     *
     * Query params opcionais:
     *   start       (RFC3339)
     *   end         (RFC3339)
     *   query       (string)
     *   calendar_id (string, default: primary)
     *   limit       (int, max: 100, default: 20)
     *   time_zone   (IANA tz, ex: America/Sao_Paulo)
     */
    public function events(Request $request): JsonResponse
    {
        try {
            // ── 1. Contexto injetado pelo middleware ai.gateway ────────────────
            $organization = $request->get('_active_organization');
            $user         = $request->get('_active_user');

            // ── 2. Autorização (Substituído pelo novo modelo) ─────────────────
            // A autorização final com scopes e identidades ocorrerá logo após validarmos o input.

            // ── 3. Validação de parâmetros ────────────────────────────────────
            $validator = Validator::make($request->all(), [
                'start'       => ['nullable', 'date'],
                'end'         => ['nullable', 'date'],
                'query'       => ['nullable', 'string', 'max:255'],
                'calendar_id' => ['nullable', 'string', 'max:255'],
                'limit'       => ['nullable', 'integer', 'min:1', 'max:100'],
                'time_zone'   => ['nullable', 'timezone'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INVALID_DATE_RANGE',
                    'message' => 'Parâmetros inválidos.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // ── 4. Validação: start < end ─────────────────────────────────────
            $startRaw = $request->query('start');
            $endRaw   = $request->query('end');

            if ($startRaw && $endRaw) {
                $startCarbon = Carbon::parse($startRaw);
                $endCarbon   = Carbon::parse($endRaw);

                if (!$startCarbon->lt($endCarbon)) {
                    return response()->json([
                        'success' => false,
                        'code'    => 'INVALID_DATE_RANGE',
                        'message' => 'O parâmetro "start" deve ser anterior a "end".',
                    ], 422);
                }
            }

            // ── 5. Resolver integração Google Workspace ────────────────────────
            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                    'message' => 'A integração com o Google Workspace não está conectada para esta organização.',
                ], 503);
            }

            // ── 5.5. Resolver Contexto de Acesso & Identidade ──────────────────
            $targetUser = null; // No futuro, se a tool aceitar "target_user_id", nós instanciaríamos o alvo aqui
            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'calendar.events.read',
                $integration,
                'google_workspace',
                $targetUser
            );

            // ── 6. Delegar ao Service ─────────────────────────────────────────
            $filters = array_filter([
                'start'       => $startRaw       ?: null,
                'end'         => $endRaw         ?: null,
                'query'       => $request->query('query'),
                'calendar_id' => $request->query('calendar_id'),
                'limit'       => $request->query('limit'),
                'time_zone'   => $request->query('time_zone'),
            ], fn($v) => !is_null($v));

            $conversationUuid = $request->header('X-Conversation-UUID');

            $data = $this->calendarService->listEvents(
                $organization,
                $integration,
                $filters,
                $user->id,
                $conversationUuid,
                $accessContext->getResolvedIdentity()
            );

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'message' => 'Você não possui permissão para acessar o calendário.',
            ], 403);

        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage(),
            ], 403);
            
        } catch (\App\Domain\Identities\Exceptions\ProviderDelegationRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage(),
            ], 503);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => 'A integração Google precisa ser reconectada.',
            ], 503);

        } catch (GoogleCalendarException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'               => 403,
                'CALENDAR_NOT_FOUND'          => 404,
                'INVALID_DATE_RANGE'          => 422,
                'GOOGLE_CALENDAR_UNAVAILABLE' => 503,
                default                       => 500,
            };

            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
            ], $httpStatus);

        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao consultar o calendário.',
            ], 500);
        }
    }

    /**
     * POST /api/ai/calendar/freebusy
     *
     * Body json:
     *   start                 (RFC3339)
     *   end                   (RFC3339)
     *   calendar_id           (string, default: primary)
     *   slot_duration_minutes (int, min: 15, max: 480, default: 30)
     */
    public function freebusy(Request $request): JsonResponse
    {
        try {
            // ── 1. Contexto injetado pelo middleware ai.gateway ────────────────
            $organization = $request->get('_active_organization');
            $user         = $request->get('_active_user');

            // ── 2. Autorização (Substituído pelo novo modelo) ─────────────────
            // A autorização final com scopes e identidades ocorrerá logo após validarmos o input.

            // ── 3. Validação de parâmetros ────────────────────────────────────
            $validator = Validator::make($request->json()->all(), [
                'start'                 => ['required', 'date'],
                'end'                   => ['required', 'date'],
                'calendar_id'           => ['nullable', 'string', 'max:255'],
                'slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INVALID_DATE_RANGE',
                    'message' => 'Parâmetros inválidos.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // ── 4. Validação: start < end ─────────────────────────────────────
            $startRaw = $request->json('start');
            $endRaw   = $request->json('end');

            $startCarbon = Carbon::parse($startRaw);
            $endCarbon   = Carbon::parse($endRaw);

            if (!$startCarbon->lt($endCarbon)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INVALID_DATE_RANGE',
                    'message' => 'O parâmetro "start" deve ser anterior a "end".',
                ], 422);
            }

            // ── 5. Resolver integração Google Workspace ────────────────────────
            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                    'message' => 'A integração com o Google Workspace não está conectada para esta organização.',
                ], 503);
            }

            // ── 5.5. Resolver Contexto de Acesso & Identidade ──────────────────
            $targetUser = null; // No futuro, se a tool aceitar "target_user_id", nós instanciaríamos o alvo aqui
            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'calendar.freebusy.read',
                $integration,
                'google_workspace',
                $targetUser
            );

            // ── 6. Delegar ao Service ─────────────────────────────────────────
            $filters = array_filter([
                'start'                 => $startRaw,
                'end'                   => $endRaw,
                'calendar_id'           => $request->json('calendar_id'),
                'slot_duration_minutes' => $request->json('slot_duration_minutes'),
            ], fn($v) => !is_null($v));

            $conversationUuid = $request->header('X-Conversation-UUID');

            $data = $this->calendarService->getFreeBusy(
                $organization,
                $integration,
                $filters,
                $user->id,
                $conversationUuid,
                $accessContext->getResolvedIdentity()
            );

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'message' => 'Você não possui permissão para consultar a disponibilidade do calendário.',
            ], 403);

        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage(),
            ], 403);
            
        } catch (\App\Domain\Identities\Exceptions\ProviderDelegationRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage(),
            ], 503);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => 'A integração Google precisa ser reconectada.',
            ], 503);

        } catch (GoogleCalendarException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'               => 403,
                'CALENDAR_NOT_FOUND'          => 404,
                'INVALID_DATE_RANGE'          => 422,
                'GOOGLE_CALENDAR_UNAVAILABLE' => 503,
                default                       => 500,
            };

            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
            ], $httpStatus);

        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao consultar a disponibilidade.',
            ], 500);
        }
    }

    /**
     * POST /api/ai/calendar/events
     * 
     * Cria um novo evento no Google Calendar.
     */
    public function createEvent(Request $request): JsonResponse
    {
        try {
            // ── 1. Contexto injetado pelo middleware ai.gateway ────────────────
            $organization = $request->attributes->get('_active_organization') ?? app(\App\Domain\Organizations\Models\Organization::class);
            $user         = $request->attributes->get('_active_user') ?? app(\App\Domain\Identity\Models\User::class);

            // ── 2. Validação de parâmetros ────────────────────────────────────
            $validator = Validator::make($request->json()->all(), [
                'title'                 => ['required', 'string', 'max:255'],
                'start'                 => ['required', 'date'],
                'end'                   => ['required', 'date', 'after:start'],
                'description'           => ['nullable', 'string'],
                'location'              => ['nullable', 'string', 'max:255'],
                'time_zone'             => ['nullable', 'timezone'],
                'attendees'             => ['nullable', 'array', 'max:20'],
                'attendees.*.user_uuid' => ['required_with:attendees', 'uuid'],
                'create_meeting'        => ['nullable', 'boolean'],
                'target_user_uuid'      => ['nullable', 'uuid'],
                'check_conflicts'       => ['nullable', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Parâmetros inválidos.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // ── 3. Confirmação (Header) ───────────────────────────────────────
            if ($request->header('X-Nodal-Action-Confirmed') !== 'true') {
                return response()->json([
                    'success' => false,
                    'code'    => 'CONFIRMATION_REQUIRED',
                    'message' => 'Você precisa confirmar esta ação antes de prosseguir.',
                ], 428);
            }

            // ── 4. Resolver integração Google Workspace ────────────────────────
            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            \Illuminate\Support\Facades\Log::info('[DEBUG_INTEGRATION_RESOLUTION] createEvent:', [
                'organization_uuid_received' => is_object($organization) ? $organization->uuid : null,
                'organization_id_resolved' => is_object($organization) ? $organization->id : null,
                'integration_found' => $integration ? true : false,
                'provider_searched' => 'google_workspace',
                'status_searched' => 'connected',
                'integration_id' => $integration ? $integration->id : null,
            ]);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                    'message' => 'A integração com o Google Workspace não está conectada para esta organização.',
                ], 503);
            }

            // ── 5. Resolver Contexto de Acesso & Identidade ──────────────────
            $targetUserUuid = $request->json('target_user_uuid');
            $targetUser = $targetUserUuid
                ? \App\Domain\Identity\Models\User::where('uuid', $targetUserUuid)->first()
                : null;

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'calendar.events.create',
                $integration,
                'google_workspace',
                $targetUser
            );

            // ── 6. Resolver attendees internos (user_uuid → ExternalIdentity → email) ──
            // O n8n NUNCA envia emails diretamente. Apenas UUIDs de usuários Nodal.
            // Regras: mesmo tenant, ExternalIdentity google_workspace vinculada, sem duplicatas, máx 20.
            $attendeesRaw  = $request->json('attendees', []);
            $attendeesMeta = []; // [{user_uuid, name, email}] — para resposta normalizada
            $googleAttendees = []; // [{email}] — para payload do Google Calendar
            $seenUuids     = []; // deduplicação

            if (!empty($attendeesRaw)) {
                // IDs de usuários da organização (para validação de tenant em batch)
                $orgUserIds = $organization->users()->pluck('users.id')->toArray();

                foreach ($attendeesRaw as $index => $att) {
                    $attUuid = $att['user_uuid'] ?? null;

                    if (!$attUuid) {
                        return response()->json([
                            'success' => false,
                            'code'    => 'ATTENDEE_INVALID',
                            'message' => "O participante na posição {$index} não possui user_uuid.",
                        ], 422);
                    }

                    // Deduplica
                    if (in_array($attUuid, $seenUuids, true)) {
                        return response()->json([
                            'success' => false,
                            'code'    => 'ATTENDEE_INVALID',
                            'message' => "O participante {$attUuid} foi enviado em duplicata.",
                        ], 422);
                    }
                    $seenUuids[] = $attUuid;

                    // Localiza o usuário
                    $attUser = \App\Domain\Identity\Models\User::where('uuid', $attUuid)->first();
                    if (!$attUser) {
                        return response()->json([
                            'success' => false,
                            'code'    => 'ATTENDEE_NOT_FOUND',
                            'message' => "Participante {$attUuid} não encontrado.",
                        ], 422);
                    }

                    // Valida tenant — o usuário deve pertencer à organização ativa
                    if (!in_array($attUser->id, $orgUserIds, true)) {
                        return response()->json([
                            'success' => false,
                            'code'    => 'ATTENDEE_NOT_ALLOWED',
                            'message' => "O participante {$attUuid} não pertence a esta organização.",
                        ], 422);
                    }

                    // Resolve ExternalIdentity google_workspace
                    $extId = $attUser->externalIdentities()
                        ->where('organization_id', $organization->id)
                        ->where('provider', 'google_workspace')
                        ->first();

                    if (!$extId || empty($extId->primary_email)) {
                        return response()->json([
                            'success' => false,
                            'code'    => 'ATTENDEE_EXTERNAL_IDENTITY_REQUIRED',
                            'message' => "O participante {$attUuid} não possui uma conta Google Workspace vinculada.",
                        ], 422);
                    }

                    $attendeesMeta[]  = [
                        'user_uuid' => $attUser->uuid,
                        'name'      => $attUser->name,
                        'email'     => $extId->primary_email,
                    ];
                    $googleAttendees[] = ['email' => $extId->primary_email];
                }
            }

            $createMeeting = (bool) $request->json('create_meeting', false);

            $eventData = [
                'summary'     => $request->json('title'),
                'start'       => $request->json('start'),
                'end'         => $request->json('end'),
                'description' => $request->json('description'),
                'location'    => $request->json('location'),
                'time_zone'   => $request->json('time_zone'),
                'attendees'   => $googleAttendees,
            ];

            // ── 7. Tratar conflitos ───────────────────────────────────────────
            if ($request->json('check_conflicts')) {
                $freeBusy = $this->calendarService->getFreeBusy(
                    $organization,
                    $integration,
                    [
                        'start'       => $eventData['start'],
                        'end'         => $eventData['end'],
                        'calendar_id' => 'primary',
                    ],
                    $user->id,
                    $request->header('X-Conversation-UUID'),
                    $accessContext->getResolvedIdentity()
                );

                if (!empty($freeBusy['busy'])) {
                    return response()->json([
                        'success' => false,
                        'code'    => 'EVENT_CONFLICT',
                        'message' => 'O horário solicitado está ocupado.',
                    ], 409);
                }
            }

            // ── 8. Idempotência ───────────────────────────────────────────────
            $idempotencyKey = $request->header('X-Idempotency-Key');
            $cacheKey = null;

            if ($idempotencyKey) {
                $targetId = $targetUser ? $targetUser->id : $user->id;
                $cacheKey = "calendar_create_event:{$organization->id}:{$targetId}:{$idempotencyKey}";

                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    return response()->json([
                        'success' => true,
                        'data'    => \Illuminate\Support\Facades\Cache::get($cacheKey),
                    ], 200);
                }
            }

            // ── 9. meetRequestId idempotente ─────────────────────────────────
            // Derivado de organization + (idempotency key ou conversation_uuid) + start.
            // Garante que retries com mesma chave → mesmo requestId → Google não cria dois Meets.
            $meetSeed = $idempotencyKey ?? $request->header('X-Conversation-UUID') ?? \Illuminate\Support\Str::uuid()->toString();
            $meetRequestId = $createMeeting
                ? hash('sha256', "nodal-meet:{$organization->id}:{$meetSeed}:{$eventData['start']}")
                : null;

            // ── 10. Delegar ao Service ────────────────────────────────────────
            $conversationUuid = $request->header('X-Conversation-UUID');

            $data = $this->calendarService->createEvent(
                $organization,
                $integration,
                $eventData,
                $user->id,
                $conversationUuid,
                $accessContext->getResolvedIdentity(),
                $attendeesMeta,
                $createMeeting,
                $meetRequestId
            );

            // ── 11. Salvar Cache de Idempotência ─────────────────────────────
            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $data, now()->addHours(24));
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'message' => 'Você não possui permissão para acessar o calendário.',
            ], 403);

        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage(),
            ], 403);
            
        } catch (\App\Domain\Identities\Exceptions\ProviderDelegationRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage(),
            ], 503);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => 'A integração Google precisa ser reconectada.',
            ], 503);

        } catch (GoogleCalendarException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'               => 403,
                'CALENDAR_NOT_FOUND'          => 404,
                'INVALID_DATE_RANGE'          => 422,
                'GOOGLE_CALENDAR_UNAVAILABLE' => 503,
                'GOOGLE_MEET_UNAVAILABLE'     => 503,
                'GOOGLE_MEET_NOT_ALLOWED'     => 403,
                default                       => 500,
            };

            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
            ], $httpStatus);

        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao criar o evento no calendário.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // updateEvent — PATCH /api/ai/calendar/events/{eventId}
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Atualiza um evento existente no Google Calendar do usuário autorizado.
     *
     * Estratégia: GET atual do Google → aplica somente campos presentes → PUT completo com ETag.
     * Campo ausente = preservar. Campo null (onde permitido) = remover.
     * Attendees: lista final de internos; externos preservados automaticamente.
     * Meet: create_meeting=true adiciona; remove_meeting=true remove; ausência = preservar.
     */
    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        try {
            // ── 1. Contexto via middleware ────────────────────────────────────
            $user         = $request->get('_active_user');
            $organization = $request->get('_active_organization');

            if (!$user || !$organization) {
                return response()->json(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'Contexto de autenticação inválido.'], 401);
            }

            // ── 2. Validação de parâmetros ────────────────────────────────────
            // Nota: usamos 'present' para distinguir campo ausente de campo null
            $validator = Validator::make($request->json()->all(), [
                'changes'                        => ['required', 'array', 'min:1'],
                'changes.title'                  => ['sometimes', 'nullable', 'string', 'max:255'],
                'changes.description'            => ['sometimes', 'nullable', 'string'],
                'changes.location'               => ['sometimes', 'nullable', 'string', 'max:255'],
                'changes.start'                  => ['sometimes', 'date'],
                'changes.end'                    => ['sometimes', 'date'],
                'changes.time_zone'              => ['sometimes', 'nullable', 'timezone'],
                'changes.attendees'              => ['sometimes', 'nullable', 'array', 'max:20'],
                'changes.attendees.*.user_uuid'  => ['required_with:changes.attendees', 'uuid'],
                'changes.create_meeting'         => ['sometimes', 'nullable', 'boolean'],
                'changes.remove_meeting'         => ['sometimes', 'nullable', 'boolean'],
                'check_conflicts'                => ['nullable', 'boolean'],
                'target_user_uuid'               => ['nullable', 'uuid'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Parâmetros inválidos.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $changes = $request->json('changes', []);

            // changes deve ter pelo menos um campo real (não só time_zone)
            $allowedChangeKeys = ['title', 'description', 'location', 'start', 'end', 'attendees', 'create_meeting', 'remove_meeting'];
            $realChanges = array_intersect_key($changes, array_flip($allowedChangeKeys));
            if (empty($realChanges)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'CHANGES_REQUIRED',
                    'message' => 'O campo changes deve conter ao menos uma alteração.',
                ], 422);
            }

            // create_meeting e remove_meeting não podem ser true simultaneamente
            $createMeeting = (bool) ($changes['create_meeting'] ?? false);
            $removeMeeting = (bool) ($changes['remove_meeting'] ?? false);
            if ($createMeeting && $removeMeeting) {
                return response()->json([
                    'success' => false,
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'create_meeting e remove_meeting não podem ser true simultaneamente.',
                ], 422);
            }

            // ── 3. Confirmação (Header) ───────────────────────────────────────
            if ($request->header('X-Nodal-Action-Confirmed') !== 'true') {
                return response()->json([
                    'success' => false,
                    'code'    => 'CONFIRMATION_REQUIRED',
                    'message' => 'Você precisa confirmar esta ação antes de prosseguir.',
                ], 428);
            }

            // ── 4. Resolver integração Google Workspace ────────────────────────
            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                    'message' => 'A integração com o Google Workspace não está conectada para esta organização.',
                ], 503);
            }

            // ── 5. Resolver Contexto de Acesso & Identidade ──────────────────
            $targetUserUuid = $request->json('target_user_uuid');
            $targetUser     = $targetUserUuid
                ? \App\Domain\Identity\Models\User::where('uuid', $targetUserUuid)->first()
                : null;

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'calendar.events.update',
                $integration,
                'google_workspace',
                $targetUser
            );

            // ── 6. Resolver attendees internos ────────────────────────────────
            // Mesmo padrão do createEvent: user_uuid → ExternalIdentity → email.
            // A chave 'attendees' deve estar PRESENTE em changes para substituição.
            // Ausente = preservar todos os attendees atuais.
            $attendeesRaw    = null; // null = ausente (preservar)
            $attendeesMeta   = [];
            $googleAttendees = [];
            $seenUuids       = [];

            if (array_key_exists('attendees', $changes)) {
                $attendeesRaw = $changes['attendees'] ?? []; // [] = remover internos

                if (!empty($attendeesRaw)) {
                    $orgUserIds = $organization->users()->pluck('users.id')->toArray();

                    foreach ($attendeesRaw as $index => $att) {
                        $attUuid = $att['user_uuid'] ?? null;

                        if (!$attUuid) {
                            return response()->json(['success' => false, 'code' => 'ATTENDEE_INVALID', 'message' => "O participante na posição {$index} não possui user_uuid."], 422);
                        }
                        if (in_array($attUuid, $seenUuids, true)) {
                            return response()->json(['success' => false, 'code' => 'ATTENDEE_INVALID', 'message' => "O participante {$attUuid} foi enviado em duplicata."], 422);
                        }
                        $seenUuids[] = $attUuid;

                        $attUser = \App\Domain\Identity\Models\User::where('uuid', $attUuid)->first();
                        if (!$attUser) {
                            return response()->json(['success' => false, 'code' => 'ATTENDEE_NOT_FOUND', 'message' => "Participante {$attUuid} não encontrado."], 422);
                        }
                        if (!in_array($attUser->id, $orgUserIds, true)) {
                            return response()->json(['success' => false, 'code' => 'ATTENDEE_NOT_ALLOWED', 'message' => "O participante {$attUuid} não pertence a esta organização."], 422);
                        }

                        $extId = $attUser->externalIdentities()
                            ->where('organization_id', $organization->id)
                            ->where('provider', 'google_workspace')
                            ->first();

                        if (!$extId || empty($extId->primary_email)) {
                            return response()->json(['success' => false, 'code' => 'ATTENDEE_EXTERNAL_IDENTITY_REQUIRED', 'message' => "O participante {$attUuid} não possui uma conta Google Workspace vinculada."], 422);
                        }

                        $attendeesMeta[]   = ['user_uuid' => $attUser->uuid, 'name' => $attUser->name, 'email' => $extId->primary_email];
                        $googleAttendees[] = ['email' => $extId->primary_email];
                    }
                }
                // Sinaliza para o service que attendees deve ser substituído
                $changes['attendees'] = $googleAttendees;
            }

            // ── 7. check_conflicts (delegado para o service) ───────────────────
            $checkConflicts = $request->json('check_conflicts') ?? false;

            // ── 8. Idempotência (namespace isolado do create) ─────────────────
            $idempotencyKey = $request->header('X-Idempotency-Key');
            $cacheKey       = null;

            if ($idempotencyKey) {
                $targetId = $targetUser ? $targetUser->id : $user->id;
                // Prefixo calendar_event_update: — NUNCA compartilhado com create
                $cacheKey = "calendar_event_update:{$organization->id}:{$targetId}:{$eventId}:{$idempotencyKey}";

                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    return response()->json([
                        'success' => true,
                        'data'    => \Illuminate\Support\Facades\Cache::get($cacheKey),
                    ], 200);
                }
            }

            // ── 9. meetRequestId idempotente (prefixo distinto do create) ─────
            $meetSeed      = $idempotencyKey ?? $request->header('X-Conversation-UUID') ?? \Illuminate\Support\Str::uuid()->toString();
            $meetRequestId = $createMeeting
                ? hash('sha256', "nodal-meet-upd:{$organization->id}:{$eventId}:{$meetSeed}")
                : null;

            // ── 10. Delegar ao Service ────────────────────────────────────────
            $conversationUuid = $request->header('X-Conversation-UUID');

            $data = $this->calendarService->updateEvent(
                $organization,
                $integration,
                $eventId,
                $changes,
                $user->id,
                $conversationUuid,
                $accessContext->getResolvedIdentity(),
                $attendeesMeta,
                $createMeeting,
                $removeMeeting,
                $meetRequestId,
                $checkConflicts
            );

            // ── 11. Salvar cache de idempotência ──────────────────────────────
            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $data, now()->addHours(24));
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ], 200);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'message' => 'Você não possui permissão para atualizar eventos do calendário.',
            ], 403);

        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException | \App\Domain\Identities\Exceptions\TargetIdentityNotFoundException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage(),
            ], 403);

        } catch (\App\Domain\Identities\Exceptions\ProviderDelegationRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage(),
            ], 503);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => 'A integração Google precisa ser reconectada.',
            ], 503);

        } catch (GoogleCalendarException $e) {
            $httpStatus = match ($e->errorCode) {
                'EVENT_NOT_FOUND'             => 404,
                'ACCESS_DENIED'               => 403,
                'EVENT_NOT_ALLOWED'           => 403,
                'EVENT_CHANGED'               => 409,
                'EVENT_CONFLICT'              => 409,
                'CALENDAR_NOT_FOUND'          => 404,
                'INVALID_DATE_RANGE'          => 422,
                'GOOGLE_CALENDAR_UNAVAILABLE' => 503,
                'GOOGLE_MEET_UNAVAILABLE'     => 503,
                'GOOGLE_MEET_NOT_ALLOWED'     => 403,
                default                       => 500,
            };

            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
            ], $httpStatus);

        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_CALENDAR_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao atualizar o evento no calendário.',
            ], 500);
        }
    }
}
