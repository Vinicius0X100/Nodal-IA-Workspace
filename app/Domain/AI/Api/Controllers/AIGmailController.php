<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\Integrations\Exceptions\GoogleGmailException;
use App\Domain\Integrations\Exceptions\GoogleReauthRequiredException;
use App\Domain\Integrations\Exceptions\IntegrationUnavailableException;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleGmailService;
use App\Domain\Permissions\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AIGmailController — READ-ONLY v1
 *
 * Responsabilidades:
 *  - Validar a requisição e parâmetros de busca.
 *  - Autorizar a capability gmail.messages.read no escopo target.
 *  - Resolver a identidade externa e delegar para GoogleGmailService.
 */
class AIGmailController
{
    public function __construct(
        private GoogleGmailService $gmailService,
        private AuthorizationService $authorizationService,
    ) {}

    /**
     * GET /api/ai/gmail/messages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // ── 1. Contexto injetado ─────────────────────────────────────────
            $organization = $request->get('_active_organization');
            $activeUser   = $request->get('_active_user');

            // ── 2. Validação ──────────────────────────────────────────────────
            $validator = Validator::make($request->all(), [
                'q'                => ['nullable', 'string', 'max:500'],
                'from'             => ['nullable', 'string', 'max:255'],
                'to'               => ['nullable', 'string', 'max:255'],
                'subject'          => ['nullable', 'string', 'max:255'],
                'after'            => ['nullable', 'date'],
                'before'           => ['nullable', 'date'],
                'is_unread'        => ['nullable', 'boolean'],
                'has_attachment'   => ['nullable', 'boolean'],
                'label'            => ['nullable', 'string', 'max:255'],
                'limit'            => ['nullable', 'integer', 'min:1', 'max:50'],
                'page_token'       => ['nullable', 'string', 'max:255'],
                'target_user_uuid' => ['nullable', 'uuid'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Parâmetros de busca inválidos.',
                    'errors'  => $validator->errors(),
                ], 400);
            }

            $filters = $validator->validated();
            $targetUserUuid = $filters['target_user_uuid'] ?? null;
            $conversationUuid = $request->header('X-Conversation-UUID');

            // ── 3. Resolução da Integração e Identidade ───────────────────────
            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INTEGRATION_UNAVAILABLE',
                    'message' => 'Integração com Google Workspace não configurada ou inativa.'
                ], 404);
            }

            $targetUser = null;
            if ($targetUserUuid) {
                $targetUser = \App\Domain\Identity\Models\User::where('uuid', $targetUserUuid)->first();
            }

            // ── 4. Autorização e Access Context ───────────────────────────────
            $accessContext = $this->authorizationService->resolveAccessContext(
                $activeUser,
                $organization,
                'gmail.messages.read',
                $integration,
                'google_workspace',
                $targetUser
            );

            // Obtém a identidade do usuário alvo (o próprio, ou se autorizado, o colega)
            $identity = $accessContext->getResolvedIdentity();

            // ── 5. Execução ───────────────────────────────────────────────────
            $result = $this->gmailService->searchMessages(
                organization: $organization,
                integration: $integration,
                filters: $filters,
                actingUserId: $activeUser->id,
                conversationUuid: $conversationUuid,
                identity: $identity
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => $e->getMessage()
            ], 401);
        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTEGRATION_UNAVAILABLE',
                'message' => $e->getMessage()
            ], 404);
        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\App\Domain\Identities\Exceptions\ProviderDelegationRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage(),
            ], 503);
        } catch (GoogleGmailException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'           => 403,
                'GMAIL_MESSAGE_NOT_FOUND' => 404,
                'GMAIL_UNAVAILABLE'       => 502,
                default                   => 500,
            };

            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage()
            ], $httpStatus);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'TARGET_USER_NOT_ALLOWED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao processar a pesquisa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/ai/gmail/messages/{messageId}
     */
    public function show(Request $request, string $messageId): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $activeUser   = $request->get('_active_user');

            $targetUserUuid = $request->query('target_user_uuid');
            $conversationUuid = $request->header('X-Conversation-UUID');

            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INTEGRATION_UNAVAILABLE',
                    'message' => 'Integração com Google Workspace não configurada.'
                ], 404);
            }

            $targetUser = null;
            if ($targetUserUuid) {
                $targetUser = \App\Domain\Identity\Models\User::where('uuid', $targetUserUuid)->first();
            }

            $accessContext = $this->authorizationService->resolveAccessContext(
                $activeUser,
                $organization,
                'gmail.messages.read',
                $integration,
                'google_workspace',
                $targetUser
            );

            $identity = $accessContext->getResolvedIdentity();

            $result = $this->gmailService->readMessage(
                organization: $organization,
                integration: $integration,
                messageId: $messageId,
                actingUserId: $activeUser->id,
                conversationUuid: $conversationUuid,
                identity: $identity
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => $e->getMessage()
            ], 401);
        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTEGRATION_UNAVAILABLE',
                'message' => $e->getMessage()
            ], 404);
        } catch (\App\Domain\Identities\Exceptions\TargetIdentityNotFoundException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        } catch (GoogleGmailException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'           => 403,
                'GMAIL_MESSAGE_NOT_FOUND' => 404,
                'GMAIL_UNAVAILABLE'       => 502,
                default                   => 500,
            };
            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage()
            ], $httpStatus);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'TARGET_USER_NOT_ALLOWED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao ler a mensagem.'
            ], 500);
        }
    }

    /**
     * GET /api/ai/gmail/messages/{messageId}/attachments/{attachmentId}
     */
    public function readAttachment(Request $request, string $messageId, string $attachmentId): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $activeUser   = $request->get('_active_user');

            $targetUserUuid = $request->query('target_user_uuid');
            $conversationUuid = $request->header('X-Conversation-UUID');

            $integration = Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INTEGRATION_UNAVAILABLE',
                    'message' => 'Integração com Google Workspace não configurada.'
                ], 404);
            }

            $targetUser = null;
            if ($targetUserUuid) {
                $targetUser = \App\Domain\Identity\Models\User::where('uuid', $targetUserUuid)->first();
            }

            $accessContext = $this->authorizationService->resolveAccessContext(
                $activeUser,
                $organization,
                'gmail.messages.read',
                $integration,
                'google_workspace',
                $targetUser
            );

            $identity = $accessContext->getResolvedIdentity();

            $result = $this->gmailService->readAttachment(
                organization: $organization,
                integration: $integration,
                messageId: $messageId,
                attachmentId: $attachmentId,
                actingUserId: $activeUser->id,
                conversationUuid: $conversationUuid,
                identity: $identity
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);

        } catch (GoogleReauthRequiredException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'GOOGLE_REAUTH_REQUIRED',
                'message' => $e->getMessage()
            ], 401);
        } catch (IntegrationUnavailableException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTEGRATION_UNAVAILABLE',
                'message' => $e->getMessage()
            ], 404);
        } catch (\App\Domain\Identities\Exceptions\TargetIdentityNotFoundException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        } catch (GoogleGmailException $e) {
            $httpStatus = match ($e->errorCode) {
                'ACCESS_DENIED'                  => 403,
                'GMAIL_MESSAGE_NOT_FOUND'        => 404,
                'GMAIL_ATTACHMENT_NOT_FOUND'     => 404,
                'ATTACHMENT_CONTENT_UNAVAILABLE' => 422,
                'ATTACHMENT_TOO_LARGE'           => 413,
                'ATTACHMENT_TYPE_UNSUPPORTED'    => 415,
                'GMAIL_UNAVAILABLE'              => 502,
                default                          => 500,
            };
            return response()->json([
                'success' => false,
                'code'    => $e->errorCode,
                'message' => $e->getMessage()
            ], $httpStatus);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'TARGET_USER_NOT_ALLOWED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocorreu um erro interno ao ler o anexo.'
            ], 500);
        }
    }
}
