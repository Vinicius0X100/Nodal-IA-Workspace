<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\AI\Models\MessageAttachment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIAttachmentsService
{
    /**
     * Resolve e valida o anexo para operações de IA.
     *
     * @param Organization $organization
     * @param User $user
     * @param string $uuid
     * @param string|null $conversationUuid
     * @return MessageAttachment
     * @throws \Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function resolveForOperation(Organization $organization, User $user, string $uuid, ?string $conversationUuid = null): MessageAttachment
    {
        // Tenant Isolation: garantir que procuramos somente dentro da organization ativa
        $attachment = MessageAttachment::where('uuid', $uuid)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$attachment) {
            throw new \Exception('Attachment not found or belongs to another organization.', 404);
        }

        // Validação estrita de contexto de conversa
        if ($conversationUuid) {
            // Usa-se first() com join/wherehas, ou se já tiver conversation_id, carrega a relacao.
            // Para ser preciso sem N+1, apenas verificamos o UUID da conversa atrelada.
            $attachmentConversationUuid = $attachment->conversation->uuid ?? null;
            if ($attachmentConversationUuid !== $conversationUuid) {
                // Falha mascarada para não expor a existência cruzada
                throw new \Exception('Attachment not found or belongs to another organization.', 404);
            }
        }

        // Validação de Acesso do Usuário (O attachment deve pertencer ao usuário)
        if ($attachment->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have permission to access this attachment.');
        }

        // Validação de Expiração
        if ($attachment->expires_at && $attachment->expires_at <= now()) {
            throw new \Exception('ATTACHMENT_EXPIRED', 410);
        }

        // Status inválido (por exemplo se explicitamente expired, mas a data não bateu)
        if ($attachment->status === 'expired') {
            throw new \Exception('ATTACHMENT_EXPIRED', 410);
        }

        // Verifica o disco
        $disk = Storage::disk('chat-attachments');
        if (!$disk->exists($attachment->storage_path)) {
            throw new \Exception('ATTACHMENT_FILE_MISSING', 404);
        }

        return $attachment;
    }

    /**
     * Valida e baixa o anexo.
     *
     * @param Organization $organization
     * @param User $user
     * @param string $uuid
     * @return StreamedResponse
     * @throws \Exception
     */
    public function download(Organization $organization, User $user, string $uuid): StreamedResponse
    {
        $attachment = $this->resolveForOperation($organization, $user, $uuid);

        $disk = Storage::disk('chat-attachments');

        // Retorna o StreamedResponse usando Storage::download que preserva mime type e define Content-Disposition: attachment
        return $disk->download(
            $attachment->storage_path, 
            $attachment->original_name, 
            [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            ]
        );
    }
}
