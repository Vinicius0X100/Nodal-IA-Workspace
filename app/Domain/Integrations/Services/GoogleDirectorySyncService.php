<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Services\GoogleTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDirectorySyncService
{
    public function __construct(
        protected GoogleTokenService $tokenService
    ) {}
    /**
     * Sincroniza dados e membros de todos os grupos previamente importados.
     */
    public function syncImportedGroups(Integration $integration): void
    {
        $groups = Group::where('integration_id', $integration->id)->get();

        foreach ($groups as $group) {
            $this->syncGroupDetails($integration, $group);
            $this->syncGroupMembers($integration, $group);
        }
    }

    /**
     * Atualiza os detalhes do grupo (nome, descrição, etc).
     */
    public function syncGroupDetails(Integration $integration, Group $group): void
    {
        if (!$integration->access_token || !$group->external_id) {
            return;
        }

        try {
            $response = Http::withToken($integration->access_token)
                ->get("https://admin.googleapis.com/admin/directory/v1/groups/{$group->external_id}");

            if ($response->successful()) {
                $data = $response->json();
                
                $group->update([
                    'name' => $data['name'] ?? $data['email'],
                    'email' => $data['email'],
                    'description' => $data['description'] ?? null,
                    'metadata_json' => $data,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erro ao sincronizar detalhes do grupo {$group->email}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincroniza os membros de um grupo específico do Google Workspace
     * e os associa aos usuários existentes no Nodal baseando-se no e-mail.
     */
    public function syncGroupMembers(Integration $integration, Group $group): void
    {
        if (!$group->external_id) {
            return;
        }

        $pageToken = null;
        $memberEmails = [];

        try {
            do {
                $response = $this->tokenService->executeWithRetry($integration, function ($accessToken) use ($group, $pageToken) {
                    return Http::withToken($accessToken)
                        ->get("https://admin.googleapis.com/admin/directory/v1/groups/{$group->external_id}/members", [
                            'maxResults' => 200,
                            'pageToken' => $pageToken,
                        ]);
                });

                if ($response->successful()) {
                    $data = $response->json();
                    $pageMembers = $data['members'] ?? [];
                    
                    foreach ($pageMembers as $member) {
                        if (isset($member['email'])) {
                            $memberEmails[] = strtolower(trim($member['email']));
                        }
                    }
                    
                    $pageToken = $data['nextPageToken'] ?? null;
                } else {
                    $pageToken = null;
                    
                    IntegrationLog::create([
                        'integration_id' => $integration->id,
                        'event' => 'sync_group_members',
                        'status' => 'error',
                        'message' => "Falha ao buscar membros do grupo {$group->email}. Status: " . $response->status(),
                    ]);
                    
                    // Proteção Explicita: Não executa $group->users()->sync([]) se a API falhou.
                    return; 
                }
            } while ($pageToken);

            $userIds = [];
            $organizationId = $integration->organization_id;

            foreach ($memberEmails as $email) {
                // Busca o usuário existente ou cria um novo
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $email, // Usa o e-mail provisoriamente se não tiver o nome detalhado
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                        'email_verified_at' => now(), // Vem de uma fonte confiável
                        'status' => 'active',
                    ]
                );

                // Garante que o usuário está vinculado à organização para aparecer no Diretório
                $user->organizations()->syncWithoutDetaching([
                    $organizationId => ['joined_at' => now()]
                ]);

                $userIds[] = $user->id;
            }
            
            // Relacionar na pivot group_user removendo quem não está mais no grupo (Sincronização estrita)
            $group->users()->sync($userIds);
            
            Log::info("Sincronização de membros concluída para o grupo {$group->email}. Membros vinculados: " . count($userIds));
            
        } catch (\Exception $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'sync_group_members_error',
                'status' => 'error',
                'message' => "Erro de execução ao sincronizar membros do grupo {$group->email}: " . $e->getMessage(),
            ]);
            Log::error("Erro no GoogleDirectorySyncService ao sincronizar membros", ['error' => $e->getMessage(), 'group_uuid' => $group->uuid]);
        }
    }
}
