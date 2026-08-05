<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Enums\IntegrationProvider;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\DTOs\CreateOrganizationData;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Actions\CreateDefaultRolesAction;
use Illuminate\Support\Facades\DB;

/**
 * Cria uma nova organização e configura toda a infraestrutura inicial:
 * - Associa o usuário criador como owner
 * - Cria roles padrão (owner, admin, member)
 * - Cria registros placeholder para integrações
 */
class CreateOrganizationAction
{
    public function __construct(
        private readonly CreateDefaultRolesAction $createDefaultRoles,
    ) {}

    public function execute(CreateOrganizationData $data, User $owner): Organization
    {
        return DB::transaction(function () use ($data, $owner) {
            // 1. Criar a organização
            $organization = Organization::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'logo' => $data->logo,
                'cnpj' => $data->cnpj,
                'address' => $data->address,
                'industry' => $data->industry,
                'settings' => $data->settings ?? $this->defaultSettings(),
            ]);

            // 2. Associar o owner
            $organization->users()->attach($owner->id, [
                'is_owner' => true,
                'joined_at' => now(),
            ]);

            // 3. Criar roles padrão e atribuir owner role ao criador
            $this->createDefaultRoles->execute($organization, $owner);

            // 4. Criar registros placeholder de integrações
            $this->createDefaultIntegrations($organization);

            return $organization->fresh();
        });
    }

    private function defaultSettings(): array
    {
        return [
            'timezone' => 'America/Sao_Paulo',
            'language' => 'pt_BR',
            'notifications_enabled' => true,
        ];
    }

    private function createDefaultIntegrations(Organization $organization): void
    {
        $integrations = [
            ['provider' => IntegrationProvider::GOOGLE_WORKSPACE->value, 'display_name' => 'Google Workspace', 'status' => IntegrationStatus::NOT_CONNECTED->value],
            ['provider' => IntegrationProvider::MICROSOFT_365->value, 'display_name' => 'Microsoft 365', 'status' => IntegrationStatus::NOT_CONNECTED->value],
            ['provider' => IntegrationProvider::SLACK->value, 'display_name' => 'Slack', 'status' => IntegrationStatus::COMING_SOON->value],
            ['provider' => IntegrationProvider::GITHUB->value, 'display_name' => 'GitHub', 'status' => IntegrationStatus::COMING_SOON->value],
            ['provider' => IntegrationProvider::HUBSPOT->value, 'display_name' => 'HubSpot', 'status' => IntegrationStatus::COMING_SOON->value],
        ];

        foreach ($integrations as $integration) {
            $organization->integrations()->create($integration);
        }
    }
}
