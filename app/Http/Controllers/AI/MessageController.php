<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Services\ConversationService;
use App\Domain\AI\Services\MessageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    public function __construct(
        private MessageService $messageService,
        private ConversationService $conversationService,
        private \App\Domain\AI\Contracts\AIProviderInterface $aiProvider,
    ) {}

    /**
     * Adiciona uma nova mensagem do usuário à conversa e busca a resposta da IA.
     */
    public function store(Request $request, string $uuid): RedirectResponse
    {
        $maxFiles = config('nodal.max_chat_attachments', 5);
        $maxSizeKilobytes = (int) config('nodal.max_upload_size_mb', 50) * 1024;

        $request->validate([
            'content' => 'nullable|string|max:32000',
            'attachments' => ['nullable', 'array', 'max:' . $maxFiles],
            'attachments.*' => ['file', 'max:' . $maxSizeKilobytes],
        ]);

        $organizationId = session('active_organization_id');

        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);

        // Salva a mensagem do usuário com possíveis anexos
        $attachments = $request->file('attachments', []);
        $content = $request->input('content', '');
        
        $userMessage = $this->messageService->addUserMessage(
            $conversation,
            $content,
            $attachments
        );

        // Disparar AI Gateway
        if ($this->aiProvider->isAvailable()) {
            $result = $this->aiProvider->chat($conversation, $userMessage);
            
            $validArtifacts = $this->validateAndNormalizeArtifacts($result->artifacts, $organizationId, $request->user());
            
            \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Post-normalization', [
                'normalized_artifact_count' => count($validArtifacts),
                'content_present' => !empty($result->content)
            ]);

            $metadata = [];
            if (!empty($validArtifacts)) {
                $metadata['artifacts'] = $validArtifacts;
            }

            $message = $this->messageService->addAssistantMessage($conversation, $result->content, $metadata);

            \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Persisted message', [
                'message_id' => $message->id,
                'metadata_json' => $message->metadata_json
            ]);
        } else {
            $this->messageService->addAssistantMessage($conversation, "O Cérebro da Inteligência Artificial não está configurado ou disponível no momento.");
        }

        return back();
    }

    private function validateAndNormalizeArtifacts(array $artifacts, int $organizationId, \App\Domain\Identity\Models\User $user): array
    {
        $valid = [];
        $authService = app(\App\Domain\Permissions\Services\AuthorizationService::class);
        $organization = \App\Domain\Organizations\Models\Organization::find($organizationId);

        foreach ($artifacts as $artifact) {
            $type = $artifact['type'] ?? null;
            $uuid = $artifact['resource_uuid'] ?? null;

            \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Received artifact', [
                'type' => $type,
                'resource_uuid' => $uuid,
            ]);

            if (!$type || !$uuid) {
                \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                    'resource_uuid' => $uuid,
                    'discard_reason' => 'INVALID_UUID_OR_TYPE',
                ]);
                continue;
            }

            if ($type === 'spreadsheet') {
                $resource = \App\Domain\Resources\Models\IntegrationResource::where('uuid', $uuid)->first();
                
                $found = $resource !== null;
                $integrationMatch = $found && $resource->integration && $resource->integration->organization_id === $organizationId;

                \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Resource lookup result', [
                    'found' => $found,
                    'resource_uuid' => $found ? $resource->uuid : null,
                    'resource_type' => $found ? ($resource->resource_type instanceof \App\Domain\Resources\Enums\ResourceType ? $resource->resource_type->value : $resource->resource_type) : null,
                    'integration_id' => $found ? $resource->integration_id : null,
                    'active_organization' => $organizationId,
                    'integration_belongs_to_active_organization' => $integrationMatch,
                ]);

                if (!$found) {
                    \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'RESOURCE_NOT_FOUND',
                    ]);
                    continue;
                }
                
                if (!$integrationMatch) {
                    \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'TENANT_MISMATCH',
                    ]);
                    continue;
                }

                $providerString = $resource->provider instanceof \App\Domain\Resources\Enums\Provider ? $resource->provider->value : clone $resource->provider;
                if (!is_string($providerString)) {
                    $providerString = (string) $resource->provider;
                }
                
                $accessContext = $authService->resolveAccessContext($user, $organization, 'resources.read', $resource->integration, $providerString);
                
                $canAccess = $authService->canAccessResource($user, $organization, $resource, $accessContext);

                \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Authorization result', [
                    'canAccessResource' => $canAccess,
                    'resource_uuid' => $uuid,
                ]);

                if (!$canAccess) {
                    \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'ACCESS_DENIED',
                    ]);
                    continue;
                }
                
                $actualResourceType = $resource->resource_type instanceof \App\Domain\Resources\Enums\ResourceType ? $resource->resource_type->value : $resource->resource_type;
                \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Type validation', [
                    'received_type' => $type,
                    'actual_type' => $actualResourceType,
                ]);

                if ($actualResourceType !== 'spreadsheet') {
                    \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'TYPE_MISMATCH',
                    ]);
                    continue;
                }

                \Illuminate\Support\Facades\Log::info('[ARTIFACT_OBSERVABILITY] Artifact accepted', [
                    'resource_uuid' => $uuid,
                ]);

                $valid[] = [
                    'type' => 'spreadsheet',
                    'resource_uuid' => $resource->uuid,
                    'title' => $artifact['title'] ?? $resource->name,
                ];
            } else {
                 \Illuminate\Support\Facades\Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                    'resource_uuid' => $uuid,
                    'discard_reason' => 'UNSUPPORTED_TYPE',
                ]);
            }
        }

        return $valid;
    }
}
