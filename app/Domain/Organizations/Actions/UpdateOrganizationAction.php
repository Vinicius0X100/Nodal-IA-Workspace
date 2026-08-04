<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateOrganizationAction
{
    public function execute(Organization $organization, array $data, ?UploadedFile $logo = null): Organization
    {
        $organization->name = $data['name'] ?? $organization->name;
        $organization->cnpj = $data['cnpj'] ?? $organization->cnpj;
        $organization->address = $data['address'] ?? $organization->address;
        $organization->industry = $data['industry'] ?? $organization->industry;
        if ($logo) {
            if ($organization->logo) {
                Storage::disk('public')->delete($organization->logo);
            }
            
            $path = $logo->store('logos', 'public');
            $organization->logo = $path;
        }

        $organization->save();

        if (isset($data['owner']) && is_array($data['owner'])) {
            $owner = $organization->owner()->first();
            if ($owner) {
                if (isset($data['owner']['name'])) {
                    $owner->name = $data['owner']['name'];
                }
                if (isset($data['owner']['email'])) {
                    $owner->email = $data['owner']['email'];
                }
                if (isset($data['owner']['password'])) {
                    $owner->password = \Illuminate\Support\Facades\Hash::make($data['owner']['password']);
                }
                $owner->save();
            }
        }

        return $organization;
    }
}
