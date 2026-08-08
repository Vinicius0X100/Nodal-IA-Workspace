<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDirectorySyncService
{
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
        if (!$integration->access_token || !$group->external_id) {
            return;
        }

        $token = $integration->access_token;
        $pageToken = null;
        $memberEmails = [];

        try {
            do {
                $response = Http::withToken($token)
                    ->get("https://admin.googleapis.com/admin/directory/v1/groups/{$group->external_id}/members", [
                        'maxResults' => 200,
                        'pageToken' => $pageToken,
                    ]);

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
                        'message' => "Falha ao buscar membros do grupo {$group->email}: " . $response->body(),
                    ]);
                    return; // Aborta a sincronização deste grupo em caso de erro na API
                }
            } while ($pageToken);

            // Se encontrou membros, faz o match por e-mail com usuários que já existem no Nodal
            if (!empty($memberEmails)) {
                // Usuários que existem no Nodal
                $userIds = User::whereIn('email', $memberEmails)->pluck('id')->toArray();
                
                // Relacionar na pivot group_user sem remover outras associações
                if (!empty($userIds)) {
                    $group->users()->syncWithoutDetaching($userIds);
                }
                
                Log::info("Sincronização de membros concluída para o grupo {$group->email}. Membros vinculados: " . count($userIds));
            }
            
        } catch (\Exception $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'sync_group_members_error',
                'status' => 'error',
                'message' => "Erro de execução ao sincronizar membros do grupo {$group->email}: " . $e->getMessage(),
            ]);
            Log::error("Erro no GoogleDirectorySyncService ao sincronizar membros", ['error' => $e->getMessage()]);
        }
    }
}
