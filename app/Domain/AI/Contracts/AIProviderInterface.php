<?php

namespace App\Domain\AI\Contracts;

use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;

/**
 * Interface para provedores de IA.
 *
 * No futuro, esta interface será implementada por:
 * - N8nProvider (via n8n workflow)
 * - OpenAIProvider (direto)
 * - AzureOpenAIProvider
 * - GeminiProvider
 * etc.
 *
 * O método chat() deve suportar streaming futuramente via Generator ou Closure de callback.
 */
interface AIProviderInterface
{
    /**
     * Envia uma mensagem para o provedor e retorna a resposta do assistente.
     *
     * @param  Conversation  $conversation  Contexto completo da conversa
     * @param  Message       $message       Última mensagem do usuário
     * @return string                       Conteúdo da resposta do assistente
     */
    public function chat(Conversation $conversation, Message $message): string;

    /**
     * Verifica se o provedor está disponível e configurado corretamente.
     */
    public function isAvailable(): bool;

    /**
     * Retorna o identificador único do provedor.
     */
    public function getProviderName(): string;
}
