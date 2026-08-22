<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Enums\MessageRole;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageService
{
    /**
     * Adiciona uma mensagem do usuário à conversa e opcionalmente processa anexos.
     *
     * @param Conversation $conversation
     * @param string $content
     * @param array<UploadedFile> $attachments
     * @return Message
     */
    public function addUserMessage(Conversation $conversation, string $content, array $attachments = []): Message
    {
        return DB::transaction(function () use ($conversation, $content, $attachments) {
            $message = $this->addMessage($conversation, MessageRole::USER, $content);

            if (!empty($attachments)) {
                $metadataAttachments = [];
                $savedPaths = [];

                try {
                    foreach ($attachments as $file) {
                        if (!$file instanceof UploadedFile) {
                            continue;
                        }

                        $originalName = $file->getClientOriginalName();
                        $mimeType = $file->getMimeType();
                        $size = $file->getSize();

                        // Armazena no disco 'chat-attachments' (storage/app/private/chat-attachments)
                        $storagePath = $file->store($conversation->organization->uuid, 'chat-attachments');

                        if (!$storagePath) {
                            continue;
                        }

                        $savedPaths[] = $storagePath;

                        $attachmentModel = $message->attachments()->create([
                            'uuid' => (string) Str::uuid(),
                            'organization_id' => $conversation->organization_id,
                            'conversation_id' => $conversation->id,
                            'user_id' => $conversation->user_id,
                            'original_name' => $originalName,
                            'storage_path' => $storagePath,
                            'mime_type' => $mimeType,
                            'size' => $size,
                            'status' => 'staged',
                            'expires_at' => now()->addDays(7),
                        ]);

                        $metadataAttachments[] = [
                            'attachment_uuid' => $attachmentModel->uuid,
                            'name' => $originalName,
                            'mime_type' => $mimeType,
                            'size' => $size,
                        ];
                    }

                    if (!empty($metadataAttachments)) {
                        $metadata = $message->metadata_json ?? [];
                        $metadata['attachments'] = $metadataAttachments;
                        $message->update(['metadata_json' => $metadata]);
                    }
                } catch (\Exception $e) {
                    // Limpeza compensatória do filesystem em caso de falha no banco
                    $disk = Storage::disk('chat-attachments');
                    foreach ($savedPaths as $path) {
                        if ($disk->exists($path)) {
                            $disk->delete($path);
                        }
                    }
                    throw $e;
                }
            }

            return $message;
        });
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
