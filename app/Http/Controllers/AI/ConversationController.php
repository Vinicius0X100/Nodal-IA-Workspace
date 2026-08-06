<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Services\ConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private \App\Domain\AI\Services\MessageService $messageService,
        private \App\Domain\AI\Contracts\AIProviderInterface $aiProvider
    ) {}

    /**
     * Exibe a tela principal do AI Assistant (sem conversa selecionada).
     */
    public function index(Request $request): Response
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $groups = $this->conversationService->listGrouped($organizationId, $userId);

        return Inertia::render('Assistant/Index', [
            'conversation' => null,
            'messages' => [],
            'groups' => $groups,
        ]);
    }

    /**
     * Cria uma nova conversa e redireciona para ela.
     */
    public function store(Request $request): RedirectResponse
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        // Generate title if initial message is provided
        $initialMessage = $request->input('message');
        $title = $initialMessage ? substr($initialMessage, 0, 30) . '...' : 'Nova Conversa';

        $conversation = $this->conversationService->create($organizationId, $userId, $title);

        if ($initialMessage) {
            $userMessage = $this->messageService->addUserMessage($conversation, $initialMessage);
            
            // Disparar AI Gateway
            if ($this->aiProvider->isAvailable()) {
                $responseContent = $this->aiProvider->chat($conversation, $userMessage);
                $this->messageService->addAssistantMessage($conversation, $responseContent);
            } else {
                $this->messageService->addAssistantMessage($conversation, "O Cérebro da Inteligência Artificial não está configurado ou disponível no momento.");
            }
        }

        return redirect()->route('assistant.show', $conversation->uuid);
    }

    /**
     * Exibe uma conversa específica com todas as suas mensagens.
     */
    public function show(Request $request, string $uuid): Response
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);
        $groups = $this->conversationService->listGrouped($organizationId, $userId);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'uuid' => $m->uuid,
                'role' => $m->role->value,
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('Assistant/Index', [
            'conversation' => [
                'uuid' => $conversation->uuid,
                'title' => $conversation->title,
                'status' => $conversation->status->value,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $messages,
            'groups' => $groups,
        ]);
    }

    /**
     * Atualiza uma conversa (Renomear ou Fixar).
     */
    public function update(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'is_pinned' => 'sometimes|required|boolean',
        ]);

        $organizationId = session('active_organization_id');
        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);

        if ($request->has('title')) {
            $conversation->title = $request->input('title');
        }

        if ($request->has('is_pinned')) {
            $conversation->is_pinned = $request->input('is_pinned');
        }

        $conversation->save();

        return back();
    }

    /**
     * Exclui uma conversa.
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $organizationId = session('active_organization_id');

        $this->conversationService->delete($organizationId, $uuid);

        return redirect()->route('assistant.index');
    }
}
