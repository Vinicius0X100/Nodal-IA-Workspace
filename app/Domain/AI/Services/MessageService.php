<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Enums\MessageRole;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;

class MessageService
{
    /**
     * Adiciona uma mensagem do usuário à conversa.
     */
    public function addUserMessage(Conversation $conversation, string $content): Message
    {
        return $this->addMessage($conversation, MessageRole::USER, $content);
    }

    /**
     * Adiciona uma mensagem do assistente à conversa.
     * Será chamado pelo AI Gateway futuramente.
     */
    public function addAssistantMessage(Conversation $conversation, string $content, array $metadata = []): Message
    {
        return $this->addMessage($conversation, MessageRole::ASSISTANT, $content, $metadata);
    }

    /**
     * Adiciona uma mensagem do sistema (ex: instruções de contexto).
     */
    public function addSystemMessage(Conversation $conversation, string $content): Message
    {
        return $this->addMessage($conversation, MessageRole::SYSTEM, $content);
    }

    /**
     * Lista todas as mensagens de uma conversa (com paginação opcional).
     */
    public function list(Conversation $conversation, int $perPage = 100): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $conversation->messages()->paginate($perPage);
    }

    /**
     * Método interno para criar uma mensagem com qualquer role.
     */
    private function addMessage(
        Conversation $conversation,
        MessageRole $role,
        string $content,
        array $metadata = []
    ): Message {
        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata_json' => !empty($metadata) ? $metadata : null,
        ]);

        // Atualiza o timestamp da conversa para ordenação no histórico
        $conversation->touch();

        return $message;
    }
}
