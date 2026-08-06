<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AITool;
use Illuminate\Database\Eloquent\Collection;

class AIToolService
{
    /**
     * Obter todas as tools ativas de uma organização.
     */
    public function getActiveToolsForOrganization(int $organizationId): Collection
    {
        return AITool::where('organization_id', $organizationId)
            ->where('enabled', true)
            ->orderBy('name')
            ->get();
    }
}
