<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identities\Exceptions\TargetIdentityNotFoundException;
use App\Domain\Integrations\Exceptions\GoogleGmailException;
use App\Domain\Integrations\Exceptions\GoogleReauthRequiredException;
use App\Domain\Integrations\Exceptions\IntegrationUnavailableException;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Downloads\Models\TemporaryDownload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GoogleGmailService — READ-ONLY v1
 *
 * Responsabilidades:
 *  - Obter access_token válido via GoogleTokenService.
 *  - Montar e executar chamadas à Google Gmail API v1.
 *  - Normalizar a resposta (extrair MIME, parsing de headers).
 *  - Lidar com erros do provider de forma controlada.
 *  - Nunca expor tokens, secrets nem payloads brutos integrais desnecessários.
 */
class GoogleGmailService
{
    private const GMAIL_API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 50;

    public function __construct(
        private GoogleTokenService $tokenService,
    ) {}

    /**
     * Pesquisa e-mails no Gmail.
     * Retorna uma lista de metadados das mensagens correspondentes à query.
     */
    public function searchMessages(
        Organization $organization,
        Integration $integration,
        array $filters,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        try {
            $limit = min((int) ($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $pageToken = $filters['page_token'] ?? null;
            $q = $this->buildQueryString($filters);

            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($q, $limit, $pageToken) {
                    $url = self::GMAIL_API_BASE . '/messages';
                    
                    $params = [
                        'maxResults' => $limit,
                        'q'          => $q,
                    ];
                    if ($pageToken) {
                        $params['pageToken'] = $pageToken;
                    }

                    return Http::withToken($accessToken)->get($url, $params);
                },
                $identity,
                ['https://www.googleapis.com/auth/gmail.readonly']
            );

            if ($response->status() === 403) {
                \Illuminate\Support\Facades\Log::debug('GMAIL_DWD_DEBUG', [
                    'service_account_client_id' => $integration->credentials['client_id'] ?? $integration->credentials['client_email'] ?? 'N/A',
                    'subject'                   => $identity ? $identity->provider_id : 'null',
                    'scopes'                    => ['https://www.googleapis.com/auth/gmail.readonly'],
                    'organization_id'           => $organization->id,
                    'integration_id'            => $integration->id,
                    'http_status'               => $response->status(),
                    'error_message'             => $response->json('error.message'),
                    'error_reasons'             => collect($response->json('error.errors', []))->pluck('reason')->toArray(),
                    'url'                       => self::GMAIL_API_BASE . '/messages'
                ]);
            }

            $this->handleResponseErrors($response, 'ai_gmail_messages_search', $organization, $actingUserId, $conversationUuid);

            $data = $response->json();
            $messages = $data['messages'] ?? [];
            $nextPageToken = $data['nextPageToken'] ?? null;
            $resultSizeEstimate = $data['resultSizeEstimate'] ?? 0;

            // Busca metadados enriquecidos para cada mensagem em lote (limitado a 50)
            $enrichedMessages = [];
            if (!empty($messages)) {
                $enrichedMessages = $this->fetchMessagesMetadata($integration, $identity, array_column($messages, 'id'));
            }

            $this->audit(
                $organization,
                $actingUserId,
                $conversationUuid,
                null,
                count($enrichedMessages),
                true,
                null,
                'ai_gmail_messages_search',
                ['q' => $q]
            );

            return [
                'messages' => $enrichedMessages,
                'next_page_token' => $nextPageToken,
                'result_size_estimate' => $resultSizeEstimate,
            ];

        } catch (GoogleReauthRequiredException | TargetIdentityNotFoundException | IntegrationUnavailableException | GoogleGmailException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleGmailService] Erro inesperado ao pesquisar emails.', [
                'organization_id' => $organization->id,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha interna ao tentar pesquisar emails.');
        }
    }

    /**
     * Lê o conteúdo integral de um e-mail.
     */
    public function readMessage(
        Organization $organization,
        Integration $integration,
        string $messageId,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($messageId) {
                    $url = self::GMAIL_API_BASE . '/messages/' . urlencode($messageId);
                    return Http::withToken($accessToken)->get($url, [
                        'format' => 'full'
                    ]);
                },
                $identity,
                ['https://www.googleapis.com/auth/gmail.readonly']
            );

            $this->handleResponseErrors($response, 'ai_gmail_message_read', $organization, $actingUserId, $conversationUuid, $messageId);

            $data = $response->json();
            
            $parsedMessage = $this->parseFullMessage($data);

            $this->audit(
                $organization,
                $actingUserId,
                $conversationUuid,
                $messageId,
                1,
                true,
                null,
                'ai_gmail_message_read'
            );

            return [
                'message' => $parsedMessage
            ];

        } catch (GoogleReauthRequiredException | TargetIdentityNotFoundException | IntegrationUnavailableException | GoogleGmailException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleGmailService] Erro inesperado ao ler email.', [
                'organization_id' => $organization->id,
                'message_id'      => $messageId,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha interna ao tentar ler o email.');
        }
    }

    public function readAttachment(
        Organization $organization,
        Integration $integration,
        string $messageId,
        string $attachmentId,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        try {
            // 1. Busca os metadados da mensagem pra validar que o anexo existe e pegar tamanho/mime type
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($messageId) {
                    $url = self::GMAIL_API_BASE . '/messages/' . urlencode($messageId);
                    return Http::withToken($accessToken)->get($url, ['format' => 'full']);
                },
                $identity,
                ['https://www.googleapis.com/auth/gmail.readonly']
            );

            $this->handleResponseErrors($response, 'ai_gmail_attachment_read', $organization, $actingUserId, $conversationUuid, $messageId);

            $data = $response->json();
            
            $targetPart = $this->findAttachmentPart($data['payload'] ?? [], $attachmentId);

            if (!$targetPart) {
                $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'GMAIL_ATTACHMENT_NOT_FOUND', 'ai_gmail_attachment_read', ['attachment_id' => $attachmentId]);
                throw new GoogleGmailException('GMAIL_ATTACHMENT_NOT_FOUND', 'O anexo especificado não pertence a esta mensagem ou não existe.');
            }

            $size = $targetPart['body']['size'] ?? 0;
            if ($size > 10485760) { // 10MB
                $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'ATTACHMENT_TOO_LARGE', 'ai_gmail_attachment_read', ['attachment_id' => $attachmentId, 'size' => $size]);
                throw new GoogleGmailException('ATTACHMENT_TOO_LARGE', 'O anexo excede o limite de tamanho suportado (10MB).');
            }

            // 2. Busca o anexo real
            $attachResponse = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($messageId, $attachmentId) {
                    $url = self::GMAIL_API_BASE . '/messages/' . urlencode($messageId) . '/attachments/' . urlencode($attachmentId);
                    return Http::withToken($accessToken)->get($url);
                },
                $identity,
                ['https://www.googleapis.com/auth/gmail.readonly']
            );

            $this->handleResponseErrors($attachResponse, 'ai_gmail_attachment_read', $organization, $actingUserId, $conversationUuid, $messageId);

            $attachData = $attachResponse->json();
            if (empty($attachData['data'])) {
                $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'ATTACHMENT_CONTENT_UNAVAILABLE', 'ai_gmail_attachment_read', ['attachment_id' => $attachmentId]);
                throw new GoogleGmailException('ATTACHMENT_CONTENT_UNAVAILABLE', 'O anexo não possui dados.');
            }

            $base64 = str_replace(['-', '_'], ['+', '/'], $attachData['data']);
            $binaryData = base64_decode($base64);
            if ($binaryData === false) {
                throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao decodificar os dados do anexo.');
            }

            // 3. Extração
            $mimeType = $targetPart['mimeType'] ?? '';
            $filename = $targetPart['filename'] ?? '';
            
            $extractor = \App\Domain\Integrations\Services\Gmail\Extractors\AttachmentExtractorFactory::make(
                $mimeType,
                $filename
            );

            $extractedContent = $extractor->extract($binaryData, $mimeType, $filename);

            $this->audit(
                $organization,
                $actingUserId,
                $conversationUuid,
                $messageId,
                1,
                true,
                null,
                'ai_gmail_attachment_read',
                [
                    'attachment_id' => $attachmentId,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'extraction_type' => $extractedContent['type'],
                    'truncated' => $extractedContent['truncated'],
                ]
            );

            return [
                'attachment' => [
                    'message_id' => $messageId,
                    'attachment_id' => $attachmentId,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'content' => $extractedContent
                ]
            ];

        } catch (GoogleReauthRequiredException | TargetIdentityNotFoundException | IntegrationUnavailableException | GoogleGmailException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[GoogleGmailService] Erro inesperado ao ler anexo.', [
                'organization_id' => $organization->id,
                'message_id'      => $messageId,
                'attachment_id'   => $attachmentId,
                'error'           => $e->getMessage(),
            ]);
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha interna ao tentar ler o anexo.');
        }
    }

    public function generateAttachmentDownloadLink(
        Organization $organization,
        Integration $integration,
        string $messageId,
        string $attachmentId,
        ?int $actingUserId = null,
        ?string $conversationUuid = null,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): array {
        try {
            $response = $this->tokenService->executeWithRetry(
                $integration,
                function (string $accessToken) use ($messageId) {
                    $url = self::GMAIL_API_BASE . '/messages/' . urlencode($messageId);
                    return Http::withToken($accessToken)->get($url, ['format' => 'full']);
                },
                $identity,
                ['https://www.googleapis.com/auth/gmail.readonly']
            );

            $this->handleResponseErrors($response, 'ai_gmail_attachment_download_link', $organization, $actingUserId, $conversationUuid, $messageId);

            $data = $response->json();
            
            $targetPart = $this->findAttachmentPart($data['payload'] ?? [], $attachmentId);

            if (!$targetPart) {
                $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'GMAIL_ATTACHMENT_NOT_FOUND', 'ai_gmail_attachment_download_link', ['attachment_id' => $attachmentId]);
                throw new GoogleGmailException('GMAIL_ATTACHMENT_NOT_FOUND', 'O anexo especificado não pertence a esta mensagem ou não existe.');
            }

            $targetAttachment = [
                'attachment_id' => $targetPart['body']['attachmentId'] ?? null,
                'filename'      => $targetPart['filename'] ?? '',
                'mime_type'     => $targetPart['mimeType'] ?? '',
                'size'          => $targetPart['body']['size'] ?? 0,
            ];

            if (($targetAttachment['size'] ?? 0) > 25000000) { // Limite do Gmail de 25MB
                $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'ATTACHMENT_TOO_LARGE', 'ai_gmail_attachment_download_link', ['attachment_id' => $attachmentId, 'size' => $targetAttachment['size'] ?? 0]);
                throw new GoogleGmailException('ATTACHMENT_TOO_LARGE', 'O anexo excede o limite de tamanho suportado para download (25MB).');
            }

            $uuid = (string) Str::uuid();
            $expiresAt = now()->addMinutes(10);

            $payload = [
                'message_id' => $messageId,
                'attachment_id' => $attachmentId,
                'identity_id' => $identity?->id,
            ];

            TemporaryDownload::create([
                'uuid' => $uuid,
                'organization_id' => $organization->id,
                'user_id' => $actingUserId,
                'provider' => 'google_workspace',
                'resource_type' => 'gmail_attachment',
                'payload' => Crypt::encrypt(json_encode($payload)),
                'filename' => $targetAttachment['filename'] ?? 'attachment',
                'mime_type' => $targetAttachment['mime_type'],
                'size' => $targetAttachment['size'],
                'expires_at' => $expiresAt,
            ]);

            $downloadUrl = url("/downloads/{$uuid}");

            $this->audit(
                $organization,
                $actingUserId,
                $conversationUuid,
                $messageId,
                1,
                true,
                null,
                'ai_gmail_attachment_download_link',
                [
                    'attachment_id' => $attachmentId,
                    'filename' => $targetAttachment['filename'] ?? '',
                    'mime_type' => $targetAttachment['mime_type'] ?? '',
                    'size' => $targetAttachment['size'] ?? 0,
                ]
            );

            return [
                'filename' => $targetAttachment['filename'] ?? 'attachment',
                'mime_type' => $targetAttachment['mime_type'],
                'size' => $targetAttachment['size'],
                'download_url' => $downloadUrl,
                'expires_at' => $expiresAt->toIso8601String()
            ];

        } catch (GoogleReauthRequiredException | TargetIdentityNotFoundException | IntegrationUnavailableException | GoogleGmailException $e) {
            throw $e;
        } catch (\Exception $e) {
            $logData = [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'message_id' => $messageId,
                'organization_id' => $organization->id ?? null,
                'user_id' => $actingUserId ?? null,
            ];

            if (class_exists('\Google\Service\Exception') && $e instanceof \Google\Service\Exception) {
                $logData['errors'] = $e->getErrors();
                $logData['status_code'] = $e->getCode();
                $logData['google_api_reason'] = json_encode($e->getErrors());
            }

            \Illuminate\Support\Facades\Log::error('Gmail attachment download link failed', $logData);

            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha interna ao tentar gerar o link de download.');
        }
    }

    public function downloadAttachmentReal(
        Integration $integration,
        string $messageId,
        string $attachmentId,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity = null
    ): string {
        $response = $this->tokenService->executeWithRetry(
            $integration,
            function (string $accessToken) use ($messageId, $attachmentId) {
                $url = self::GMAIL_API_BASE . '/messages/' . urlencode($messageId) . '/attachments/' . urlencode($attachmentId);
                return Http::withToken($accessToken)->get($url);
            },
            $identity,
            ['https://www.googleapis.com/auth/gmail.readonly']
        );

        if ($response->status() === 401) {
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            throw new GoogleGmailException('ACCESS_DENIED', 'Acesso negado ao Gmail.');
        }

        if ($response->status() === 404) {
            throw new GoogleGmailException('GMAIL_ATTACHMENT_NOT_FOUND', 'O anexo não foi encontrado no Gmail.');
        }

        if (!$response->successful()) {
            throw new GoogleGmailException('GMAIL_UNAVAILABLE', 'O Gmail retornou um erro inesperado.');
        }

        $attachData = $response->json();
        if (empty($attachData['data'])) {
            throw new GoogleGmailException('ATTACHMENT_CONTENT_UNAVAILABLE', 'O anexo não possui dados: ' . json_encode($attachData));
        }

        $base64 = str_replace(['-', '_'], ['+', '/'], $attachData['data']);
        $binaryData = base64_decode($base64);

        if ($binaryData === false) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao decodificar os dados do anexo.');
        }

        return $binaryData;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function handleResponseErrors(
        \Illuminate\Http\Client\Response $response,
        string $action,
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        ?string $messageId = null
    ): void {
        if ($response->status() === 401) {
            $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'GOOGLE_REAUTH_REQUIRED', $action);
            throw new GoogleReauthRequiredException('A integração Google precisa ser reconectada. O token não é mais válido.');
        }

        if ($response->status() === 403) {
            $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'ACCESS_DENIED', $action);
            throw new GoogleGmailException('ACCESS_DENIED', 'Acesso negado ao Gmail.');
        }

        if ($response->status() === 404) {
            $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'GMAIL_MESSAGE_NOT_FOUND', $action);
            throw new GoogleGmailException('GMAIL_MESSAGE_NOT_FOUND', 'A mensagem não foi encontrada.');
        }

        if (!$response->successful()) {
            $this->audit($organization, $actingUserId, $conversationUuid, $messageId, 0, false, 'GMAIL_UNAVAILABLE', $action);
            
            \Illuminate\Support\Facades\Log::error('Gmail attachment download link failed', [
                'exception_class' => 'HttpResponseException',
                'exception_message' => 'O Gmail retornou um erro inesperado: ' . $response->body(),
                'exception_code' => $response->status(),
                'status_code' => $response->status(),
                'google_api_reason' => $response->json('error.message') ?? $response->body(),
                'message_id' => $messageId,
                'organization_id' => $organization->id ?? null,
                'user_id' => $actingUserId ?? null,
            ]);

            throw new GoogleGmailException('GMAIL_UNAVAILABLE', 'O Gmail retornou um erro inesperado.');
        }
    }

    /**
     * Normaliza os parâmetros de busca para a sintaxe `q` do Gmail.
     */
    private function buildQueryString(array $filters): string
    {
        // Se `q` foi passado diretamente, usa como base, mas valida
        $q = trim($filters['q'] ?? '');
        
        $parts = [];
        if ($q !== '') {
            $parts[] = $q;
        }

        if (!empty($filters['from'])) {
            $parts[] = 'from:(' . trim($filters['from']) . ')';
        }
        if (!empty($filters['to'])) {
            $parts[] = 'to:(' . trim($filters['to']) . ')';
        }
        if (!empty($filters['subject'])) {
            $parts[] = 'subject:(' . trim($filters['subject']) . ')';
        }
        if (!empty($filters['after'])) {
            // O Gmail aceita YYYY/MM/DD
            $parts[] = 'after:' . date('Y/m/d', strtotime($filters['after']));
        }
        if (!empty($filters['before'])) {
            $parts[] = 'before:' . date('Y/m/d', strtotime($filters['before']));
        }
        if (isset($filters['is_unread']) && filter_var($filters['is_unread'], FILTER_VALIDATE_BOOLEAN)) {
            $parts[] = 'is:unread';
        }
        if (isset($filters['has_attachment']) && filter_var($filters['has_attachment'], FILTER_VALIDATE_BOOLEAN)) {
            $parts[] = 'has:attachment';
        }
        if (!empty($filters['label'])) {
            $parts[] = 'label:' . trim($filters['label']);
        }

        return implode(' ', $parts);
    }

    /**
     * Busca os metadados das mensagens em lote usando `format=metadata`.
     */
    private function fetchMessagesMetadata(
        Integration $integration,
        ?\App\Domain\Identities\Models\ExternalIdentity $identity,
        array $messageIds
    ): array {
        // Para V1, fazemos chamadas sequenciais para evitar complexidade extrema de batch do Http client.
        // O max limit é 50.
        $enriched = [];

        $scopes = ['https://www.googleapis.com/auth/gmail.readonly'];
        $accessToken = $identity 
            ? $this->tokenService->getDelegatedAccessToken($integration, $identity, $scopes)
            : $this->tokenService->getValidAccessToken($integration);

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($messageIds, $accessToken) {
            $reqs = [];
            foreach ($messageIds as $id) {
                $url = self::GMAIL_API_BASE . '/messages/' . urlencode($id) . '?format=metadata&metadataHeaders=From&metadataHeaders=To&metadataHeaders=Subject&metadataHeaders=Date';
                $reqs[] = $pool->withToken($accessToken)->get($url);
            }
            return $reqs;
        });

        foreach ($responses as $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                $enriched[] = $this->parseMetadataMessage($response->json());
            }
        }

        return $enriched;
    }

    /**
     * Converte headers de payload do Gmail (array {name, value}) em array associativo simples
     */
    private function parseHeaders(array $payloadHeaders): array
    {
        $headers = [];
        foreach ($payloadHeaders as $h) {
            $headers[strtolower($h['name'])] = $h['value'];
        }
        return $headers;
    }

    /**
     * Normaliza a mensagem para a lista de resultados (metadata format)
     */
    private function parseMetadataMessage(array $data): array
    {
        $payload = $data['payload'] ?? [];
        $headers = $this->parseHeaders($payload['headers'] ?? []);

        return [
            'message_id' => $data['id'] ?? null,
            'thread_id'  => $data['threadId'] ?? null,
            'from'       => $this->parseEmailAddress($headers['from'] ?? ''),
            'to'         => $this->parseEmailAddressesList($headers['to'] ?? ''),
            'subject'    => $headers['subject'] ?? '',
            'date'       => $headers['date'] ?? '',
            'snippet'    => $data['snippet'] ?? '',
            'unread'     => in_array('UNREAD', $data['labelIds'] ?? []),
            'has_attachment' => $this->checkIfHasAttachment($payload),
            'labels'     => $data['labelIds'] ?? [],
        ];
    }

    /**
     * Normaliza a mensagem completa
     */
    private function parseFullMessage(array $data): array
    {
        $payload = $data['payload'] ?? [];
        $headers = $this->parseHeaders($payload['headers'] ?? []);

        $parsedParts = $this->parseParts($payload);

        return [
            'message_id' => $data['id'] ?? null,
            'thread_id'  => $data['threadId'] ?? null,
            'from'       => $this->parseEmailAddress($headers['from'] ?? ''),
            'to'         => $this->parseEmailAddressesList($headers['to'] ?? ''),
            'cc'         => $this->parseEmailAddressesList($headers['cc'] ?? ''),
            'bcc'        => $this->parseEmailAddressesList($headers['bcc'] ?? ''), // Bcc normalmente não vem do Gmail se não formos o remetente e o provedor esconder
            'reply_to'   => $this->parseEmailAddress($headers['reply-to'] ?? ''),
            'subject'    => $headers['subject'] ?? '',
            'date'       => $headers['date'] ?? '',
            'snippet'    => $data['snippet'] ?? '',
            'body'       => [
                'text' => $parsedParts['text_plain'],
                'html_available' => !empty($parsedParts['text_html'])
            ],
            'attachments' => $parsedParts['attachments'],
            'labels'     => $data['labelIds'] ?? [],
        ];
    }

    /**
     * Percorre o array de partes recursivamente
     */
    private function parseParts(array $payload): array
    {
        $result = [
            'text_plain' => '',
            'text_html' => '',
            'attachments' => []
        ];

        // Se não tiver parts, o corpo pode estar na raiz (ex: email puro text/plain simples)
        if (empty($payload['parts'])) {
            $this->extractPartData($payload, $result);
            return $result;
        }

        $this->traverseParts($payload['parts'], $result);
        
        // Fallback: se tiver html mas não plain, cria um fallback básico de plain text removendo tags
        if (empty($result['text_plain']) && !empty($result['text_html'])) {
            $result['text_plain'] = strip_tags($result['text_html']);
        }

        return $result;
    }

    private function traverseParts(array $parts, array &$result): void
    {
        foreach ($parts as $part) {
            $this->extractPartData($part, $result);
            
            if (!empty($part['parts'])) {
                $this->traverseParts($part['parts'], $result);
            }
        }
    }

    private function extractPartData(array $part, array &$result): void
    {
        $mimeType = $part['mimeType'] ?? '';
        $filename = $part['filename'] ?? '';
        $body = $part['body'] ?? [];

        // É attachment/inline
        if (!empty($filename) || (isset($body['attachmentId']) && !empty($body['attachmentId']))) {
            $result['attachments'][] = [
                'attachment_id' => $body['attachmentId'] ?? null,
                'filename'      => $filename,
                'mime_type'     => $mimeType,
                'size'          => $body['size'] ?? 0,
            ];
            return;
        }

        // É texto base
        if ($mimeType === 'text/plain') {
            $data = $body['data'] ?? '';
            if ($data) {
                $result['text_plain'] .= $this->decodeBase64Url($data) . "\n";
            }
        } else if ($mimeType === 'text/html') {
            $data = $body['data'] ?? '';
            if ($data) {
                $result['text_html'] .= $this->decodeBase64Url($data) . "\n";
            }
        }
    }

    /**
     * Decodifica a string customizada Base64Url do Google
     */
    private function decodeBase64Url(string $data): string
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64) ?: '';
    }

    /**
     * Verifica recursivamente se o payload tem attachment (apenas olhando atributos)
     */
    private function checkIfHasAttachment(array $part): bool
    {
        if (!empty($part['filename']) || (isset($part['body']['attachmentId']) && !empty($part['body']['attachmentId']))) {
            return true;
        }

        if (!empty($part['parts'])) {
            foreach ($part['parts'] as $p) {
                if ($this->checkIfHasAttachment($p)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Procura recursivamente pela part de anexo que corresponda ao attachmentId.
     */
    private function findAttachmentPart(array $part, string $attachmentId): ?array
    {
        if (($part['body']['attachmentId'] ?? null) === $attachmentId) {
            return $part;
        }

        foreach (($part['parts'] ?? []) as $child) {
            $found = $this->findAttachmentPart($child, $attachmentId);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Faz parse de strings no formato "Nome <email@dominio.com>" ou "email@dominio.com"
     */
    private function parseEmailAddress(string $raw): array
    {
        $raw = trim($raw);
        if (!$raw) {
            return ['name' => '', 'email' => ''];
        }

        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $raw, $matches)) {
            $name = trim(trim($matches[1]), '"\'');
            return [
                'name'  => $name,
                'email' => strtolower(trim($matches[2])),
            ];
        }

        return [
            'name'  => '',
            'email' => strtolower($raw),
        ];
    }

    private function parseEmailAddressesList(string $rawList): array
    {
        if (trim($rawList) === '') return [];
        
        $list = [];
        $parts = explode(',', $rawList);
        foreach ($parts as $part) {
            $parsed = $this->parseEmailAddress($part);
            if ($parsed['email']) {
                $list[] = $parsed;
            }
        }
        return $list;
    }

    private function audit(
        Organization $organization,
        ?int $actingUserId,
        ?string $conversationUuid,
        ?string $messageId,
        int $resultCount,
        bool $allowed,
        ?string $errorCode,
        string $action,
        array $metadata = []
    ): void {
        try {
            AuditLog::create([
                'organization_id'   => $organization->id,
                'user_id'           => $actingUserId,
                'action'            => $action,
                'entity_type'       => Organization::class,
                'entity_id'         => $organization->id,
                'ip_address'        => request()->ip() ?? '127.0.0.1',
                'user_agent'        => request()->userAgent() ?? 'Nodal Agent',
                'metadata'          => array_filter(array_merge([
                    'conversation_uuid' => $conversationUuid,
                    'allowed'           => $allowed,
                    'error_code'        => $errorCode,
                    'message_id'        => $messageId,
                    'result_count'      => $resultCount,
                ], $metadata), fn($v) => !is_null($v)),
            ]);
        } catch (\Throwable $e) {
            Log::error('[GoogleGmailService] Erro ao gravar audit log.', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
