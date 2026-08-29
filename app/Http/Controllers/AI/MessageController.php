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
            $metadata = [];
            if (!empty($validArtifacts)) {
                $metadata['artifacts'] = $validArtifacts;
            }

            $this->messageService->addAssistantMessage($conversation, $result->content, $metadata);
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
            if (!is_array($artifact)) continue;

            $uuid = $artifact['resource_uuid'] ?? null;
            if (!$uuid || !\Illuminate\Support\Str::isUuid($uuid)) {
                continue;
            }

            // Localiza IntegrationResource
            $resource = \App\Domain\Resources\Models\IntegrationResource::where('uuid', $uuid)
                ->whereHas('integration', function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId);
                })
                ->first();

            if (!$resource) {
                continue;
            }

            // Verifica permissão granular
            if (!$authService->canAccessResource($user, $organization, $resource)) {
                continue;
            }

            // Normaliza title e type a partir da fonte real
            $valid[] = [
                'type' => $resource->resource_type instanceof \App\Domain\Resources\Enums\ResourceType ? $resource->resource_type->value : $resource->resource_type,
                'resource_uuid' => $resource->uuid,
                'title' => $resource->name,
            ];
        }

        return $valid;
    }
}
