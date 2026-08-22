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
        // Tenant Isolation: garantir que procuramos somente dentro da organization ativa
        $attachment = MessageAttachment::where('uuid', $uuid)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$attachment) {
            throw new \Exception('Attachment not found or belongs to another organization.', 404);
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
