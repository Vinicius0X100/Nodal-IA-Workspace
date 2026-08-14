<?php

namespace App\Http\Controllers\Downloads;

use App\Http\Controllers\Controller;
use App\Domain\Downloads\Models\TemporaryDownload;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleGmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(
        private readonly GoogleGmailService $gmailService
    ) {}

    public function show(Request $request, string $uuid)
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $download = TemporaryDownload::where('uuid', $uuid)->first();

        if (!$download) {
            abort(404, 'Link de download não encontrado.');
        }

        if ($download->expires_at->isPast()) {
            abort(410, 'Este link de download expirou.');
        }

        if ($download->user_id !== $userId || $download->organization_id !== $organizationId) {
            abort(403, 'Acesso não autorizado a este download.');
        }

        try {
            $payload = json_decode(Crypt::decrypt($download->payload), true);
        } catch (\Exception $e) {
            abort(500, 'Falha ao descriptografar dados do download.');
        }

        if ($download->provider === 'google_workspace' && $download->resource_type === 'gmail_attachment') {
            return $this->handleGmailAttachmentDownload($download, $payload, $organizationId);
        }

        abort(400, 'Tipo de recurso não suportado.');
    }

    private function handleGmailAttachmentDownload(TemporaryDownload $download, array $payload, int $organizationId)
    {
        $integration = Integration::where('organization_id', $organizationId)
            ->where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->first();

        if (!$integration) {
            abort(404, 'Integração Google Workspace indisponível.');
        }

        $identity = null;
        if (!empty($payload['identity_id'])) {
            $identity = \App\Domain\Identities\Models\ExternalIdentity::find($payload['identity_id']);
            if (!$identity) {
                abort(404, 'Identidade externa não encontrada.');
            }
        }

        try {
            $binaryData = $this->gmailService->downloadAttachmentReal(
                integration: $integration,
                messageId: $payload['message_id'],
                attachmentId: $payload['attachment_id'],
                identity: $identity
            );
        } catch (\Exception $e) {
            abort(500, 'Falha ao baixar anexo da API do Google: ' . $e->getMessage());
        }

        return response()->streamDownload(function () use ($binaryData) {
            echo $binaryData;
        }, $download->filename, [
            'Content-Type' => $download->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '\\"', $download->filename) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
