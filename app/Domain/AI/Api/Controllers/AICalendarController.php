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
                'title'           => ['required', 'string', 'max:255'],
                'start'           => ['required', 'date'],
                'end'             => ['required', 'date', 'after:start'],
                'description'     => ['nullable', 'string'],
                'location'        => ['nullable', 'string', 'max:255'],
                'time_zone'       => ['nullable', 'timezone'],
                'attendees'       => ['nullable', 'array'],
                'attendees.*.email' => ['nullable', 'email'],
                'attendees.*.user_uuid' => ['nullable', 'uuid'],
                'create_meeting'  => ['nullable', 'boolean'],
                'target_user_uuid'=> ['nullable', 'uuid'],
                'check_conflicts' => ['nullable', 'boolean'],
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
                'calendar.events.create', // scope
                $integration,
                'google_workspace',
                $targetUser
            );

            // ── 6. Tratar attendees internos (UUID -> Email) ─────────────────
            $attendees = [];
            if ($request->json('attendees')) {
                foreach ($request->json('attendees') as $att) {
                    if (!empty($att['email'])) {
                        $attendees[] = ['email' => $att['email']];
                    } elseif (!empty($att['user_uuid'])) {
                        $attUser = \App\Domain\Identity\Models\User::where('uuid', $att['user_uuid'])->first();
                        if ($attUser) {
                            $extId = $attUser->externalIdentities()
                                ->where('organization_id', $organization->id)
                                ->where('provider', 'google_workspace')
                                ->first();
                            if ($extId && $extId->primary_email) {
                                $attendees[] = ['email' => $extId->primary_email];
                            }
                        }
                    }
                }
            }

            $eventData = [
                'summary'        => $request->json('title'),
                'start'          => $request->json('start'),
                'end'            => $request->json('end'),
                'description'    => $request->json('description'),
                'location'       => $request->json('location'),
                'time_zone'      => $request->json('time_zone'),
                'attendees'      => $attendees,
                'create_meeting' => $request->json('create_meeting', false),
            ];

            // ── 7. Tratar conflitos ───────────────────────────────────────────
            if ($request->json('check_conflicts')) {
                $freeBusy = $this->calendarService->getFreeBusy(
                    $organization,
                    $integration,
                    [
                        'start' => $eventData['start'],
                        'end'   => $eventData['end'],
                        'calendar_id' => 'primary'
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

            // ── 8. Idempotência (Cache) ───────────────────────────────────────
            $idempotencyKey = $request->header('X-Idempotency-Key');
            $cacheKey = null;

            if ($idempotencyKey) {
                $targetId = $targetUser ? $targetUser->id : $user->id;
                $cacheKey = "calendar_create_event:{$organization->id}:{$targetId}:{$idempotencyKey}";
                
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    return response()->json([
                        'success' => true,
                        'data'    => \Illuminate\Support\Facades\Cache::get($cacheKey)
                    ], 200);
                }
            }

            // ── 9. Delegar ao Service ─────────────────────────────────────────
            $conversationUuid = $request->header('X-Conversation-UUID');

            $data = $this->calendarService->createEvent(
                $organization,
                $integration,
                $eventData,
                $user->id,
                $conversationUuid,
                $accessContext->getResolvedIdentity()
            );

            // Salva no cache se tiver key
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
}
