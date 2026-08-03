<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Organizations\DTOs\UpdateOrganizationData;
use App\Domain\Organizations\Models\Organization;

class UpdateOrganizationAction
{
    public function execute(Organization $organization, UpdateOrganizationData $data): Organization
    {
        $updateData = array_filter([
            'name' => $data->name,
            'logo' => $data->logo,
            'settings' => $data->settings,
        ], fn ($value) => $value !== null);

        if (!empty($updateData)) {
            $organization->update($updateData);
        }

        return $organization->fresh();
    }
}
