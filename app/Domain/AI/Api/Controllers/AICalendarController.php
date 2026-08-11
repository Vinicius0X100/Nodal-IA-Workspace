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

            // ── 2. Autorização ────────────────────────────────────────────────
            $this->authorizationService->authorize($user, $organization, 'calendar.events.read');

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

            // ── 2. Autorização ────────────────────────────────────────────────
            $this->authorizationService->authorize($user, $organization, 'calendar.freebusy.read');

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
}
