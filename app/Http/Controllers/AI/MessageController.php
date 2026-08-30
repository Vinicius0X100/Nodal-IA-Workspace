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
        $normalizer = app(\App\Domain\AI\Services\AIArtifactNormalizer::class);
        return $normalizer->normalize($artifacts, $organizationId, $user);
    }
}
