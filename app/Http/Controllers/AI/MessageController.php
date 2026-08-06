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
        $request->validate([
            'content' => 'required|string|max:32000',
        ]);

        $organizationId = session('active_organization_id');

        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);

        // Salva a mensagem do usuário
        $userMessage = $this->messageService->addUserMessage(
            $conversation,
            $request->input('content')
        );

        // Disparar AI Gateway
        if ($this->aiProvider->isAvailable()) {
            $responseContent = $this->aiProvider->chat($conversation, $userMessage);
            $this->messageService->addAssistantMessage($conversation, $responseContent);
        } else {
            $this->messageService->addAssistantMessage($conversation, "O Cérebro da Inteligência Artificial não está configurado ou disponível no momento.");
        }

        return back();
    }
}
