<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Services\AIAttachmentsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIAttachmentsController
{
    public function __construct(
        private AIAttachmentsService $service
    ) {}

    /**
     * Download do anexo pelo n8n
     * 
     * Retorna o arquivo binário direto se autorizado e dentro do prazo de validade.
     */
    public function download(Request $request, string $uuid)
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            if (!$organization || !$user) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Missing AI Gateway context.');
            }

            return $this->service->download($organization, $user, $uuid);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error downloading attachment');
        }
    }

    /**
     * Trata as exceções seguindo os padrões do AI Gateway
     */
    private function handleException(\Exception $e, string $defaultMessage)
    {
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        }

        $errorCode = $e->getCode();
        $status = 500;
        $appCode = 'INTERNAL_ERROR';

        if (is_numeric($errorCode)) {
            $statusInt = (int)$errorCode;
            if ($statusInt >= 400 && $statusInt < 600) {
                $status = $statusInt;
            }
            if ($status === 404) {
                $appCode = 'RESOURCE_NOT_FOUND';
            } elseif ($status === 403) {
                $appCode = 'ACCESS_DENIED';
            } elseif ($status === 400) {
                $appCode = 'BAD_REQUEST';
            } elseif ($status === 410) {
                $appCode = 'RESOURCE_GONE';
            }
        } 
        
        $message = $e->getMessage();
        
        // Custom string code handling (like ATTACHMENT_EXPIRED, ATTACHMENT_FILE_MISSING)
        if (in_array($message, ['ATTACHMENT_EXPIRED', 'ATTACHMENT_FILE_MISSING'])) {
            $appCode = $message;
            $message = $defaultMessage;
            if ($appCode === 'ATTACHMENT_EXPIRED') $status = 410;
            if ($appCode === 'ATTACHMENT_FILE_MISSING') $status = 404;
        } elseif ($message === 'Attachment not found or belongs to another organization.') {
            $appCode = 'ATTACHMENT_NOT_FOUND';
            $status = 404;
            $message = $defaultMessage;
        }

        return response()->json([
            'success' => false,
            'code' => $appCode,
            'message' => $message !== $defaultMessage ? $defaultMessage . ': ' . $message : $message
        ], $status);
    }
}
