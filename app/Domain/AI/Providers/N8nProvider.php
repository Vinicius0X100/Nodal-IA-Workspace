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
        
        // Em um cenário real, buscaríamos as integrações ativas dinamicamente
        $activeIntegrations = ['google_workspace']; // Stub

        // Montar Histórico
        $history = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'role' => $msg->role->value,
                    'content' => $msg->content,
                ];
            })
            ->toArray();

        // Adicionar uma mensagem de sistema injetada
        $systemMessage = [
            'role' => 'system',
            'content' => "Você é o assistente inteligente do sistema Nodal. O usuário atual é {$user->name} ({$user->email}) e pertence à organização {$organization->name}. Você tem acesso às ferramentas associadas às seguintes integrações ativas: " . implode(', ', $activeIntegrations) . ". Ajude o usuário de forma clara e objetiva.",
        ];

        array_unshift($history, $systemMessage);

        $payload = [
            'context' => [
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'active_integrations' => $activeIntegrations,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'conversation_uuid' => $conversation->uuid,
                'current_date' => now()->toIso8601String(),
            ],
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
