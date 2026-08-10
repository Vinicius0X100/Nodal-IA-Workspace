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
        Log::info('[Google Group Sync] ENTER syncGroupMembers', [
            'integration_id' => $integration->id,
            'group_uuid' => $group->uuid,
            'has_token' => !empty($integration->access_token),
            'has_external_id' => !empty($group->external_id),
        ]);

        if (!$group->external_id) {
            return;
        }

        $pageToken = null;
        $memberEmails = [];
        
        $diagnosticLogs = [
            'group_uuid' => $group->uuid,
            'group_email' => $group->email,
            'external_id' => $group->external_id,
            'http_status' => null,
            'members_count' => 0,
            'member_emails' => [],
            'matched_users' => [],
            'created_users' => [],
            'user_ids' => [],
            'pivot_count_after_sync' => null,
            'errors' => []
        ];

        try {
            do {
                $response = $this->tokenService->executeWithRetry($integration, function ($accessToken) use ($group, $pageToken) {
                    return Http::withToken($accessToken)
                        ->get("https://admin.googleapis.com/admin/directory/v1/groups/{$group->external_id}/members", [
                            'maxResults' => 200,
                            'pageToken' => $pageToken,
                        ]);
                });
                    
                $diagnosticLogs['http_status'] = $response->status();

                if ($response->successful()) {
                    $data = $response->json();
                    $pageMembers = $data['members'] ?? [];
                    
                    if (empty($pageMembers)) {
                        Log::info("[Google Group Sync] GOOGLE_RETURNED_ZERO_MEMBERS for group {$group->email}", [
                            'group_uuid' => $group->uuid,
                            'external_id' => $group->external_id
                        ]);
                    }
                    
                    foreach ($pageMembers as $member) {
                        $diagnosticLogs['members_count']++;
                        if (isset($member['email'])) {
                            $memberEmails[] = strtolower(trim($member['email']));
                        }
                    }
                    
                    $pageToken = $data['nextPageToken'] ?? null;
                } else {
                    $pageToken = null;
                    
                    $errorBody = $response->json() ?? $response->body();
                    $diagnosticLogs['errors'][] = $errorBody;
                    
                    Log::info("[Google Group Sync] API Failed", [
                        'http_status' => $response->status(),
                        'body' => $errorBody,
                        'group_uuid' => $group->uuid,
                    ]);

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

            $diagnosticLogs['member_emails'] = $memberEmails;

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
                
                if ($user->wasRecentlyCreated) {
                    $diagnosticLogs['created_users'][] = ['email' => $email, 'uuid' => $user->uuid ?? null, 'id' => $user->id];
                } else {
                    $diagnosticLogs['matched_users'][] = ['email' => $email, 'uuid' => $user->uuid ?? null, 'id' => $user->id];
                }

                // Garante que o usuário está vinculado à organização para aparecer no Diretório
                $user->organizations()->syncWithoutDetaching([
                    $organizationId => ['joined_at' => now()]
                ]);

                $userIds[] = $user->id;
            }
            
            $diagnosticLogs['user_ids'] = $userIds;
            
            // Log antes do sync
            Log::info("[Google Group Sync] Preparando para sync()", [
                'group_email' => $group->email,
                'user_ids_array' => $userIds
            ]);

            // Relacionar na pivot group_user removendo quem não está mais no grupo (Sincronização estrita)
            $group->users()->sync($userIds);
            
            // Log após o sync
            $diagnosticLogs['pivot_count_after_sync'] = $group->users()->count();
            Log::info("[Google Group Sync] Sincronização Finalizada", $diagnosticLogs);
            
        } catch (\Exception $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'sync_group_members_error',
                'status' => 'error',
                'message' => "Erro de execução ao sincronizar membros do grupo {$group->email}: " . $e->getMessage(),
            ]);
            Log::error("[Google Group Sync] Erro no GoogleDirectorySyncService ao sincronizar membros", ['error' => $e->getMessage(), 'group_uuid' => $group->uuid]);
        }
    }
}
