<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProviderInterface;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Domain\Organizations\Models\Organization;

class N8nProvider implements AIProviderInterface
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.webhook_url', '');
    }

    public function chat(Conversation $conversation, Message $message): string
    {
        if (!$this->isAvailable()) {
            return "O Cérebro da Inteligência Artificial (n8n) não está configurado. Verifique as configurações de ambiente (N8N_WEBHOOK_URL).";
        }

        $user = Auth::user();
        $organization = Organization::find($conversation->organization_id);

        // Montar Histórico
        $history = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                $item = [
                    'role' => $msg->role->value,
                    'content' => $msg->content,
                ];

                // Recarrega a relação de attachments para garantir dados frescos do banco
                $msg->load('attachments');

                if ($msg->attachments && $msg->attachments->isNotEmpty()) {
                    $item['attachments'] = $msg->attachments->map(function ($att) {
                        return [
                            'attachment_uuid' => $att->uuid,
                            'name' => $att->original_name,
                            'mime_type' => $att->mime_type,
                            'size' => $att->size,
                        ];
                    })->toArray();
                } elseif (!empty($msg->metadata_json['attachments'])) {
                    $item['attachments'] = $msg->metadata_json['attachments'];
                }

                return $item;
            })
            ->toArray();

        // Agora enviamos um payload super leve, apenas com os UUIDs.
        // O n8n usará as Tools (nós HTTP conectando na AI API do Nodal)
        // para buscar o contexto da organização e do usuário sob demanda.
        $payload = [
            'organization_uuid' => $organization->uuid,
            'conversation_uuid' => $conversation->uuid,
            'user_uuid' => $user->uuid,
            'messages' => $history,
        ];

        try {
            // Disparar requisição para o n8n
            $response = Http::timeout(60)->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['response'])) {
                    return (string) $data['response'];
                }
                
                if (is_string($data) && !empty($data)) {
                    return $data;
                }
                
                return $response->body() ?: 'A IA processou a solicitação mas retornou uma resposta vazia.';
            }

            Log::error('N8nProvider error response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return "Houve um problema de comunicação com o serviço de Inteligência Artificial. (Status: {$response->status()})";

        } catch (\Exception $e) {
            Log::error('N8nProvider exception', ['message' => $e->getMessage()]);
            return "Falha de conexão com a IA: " . $e->getMessage();
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->webhookUrl);
    }

    public function getProviderName(): string
    {
        return 'n8n_gateway';
    }
}
