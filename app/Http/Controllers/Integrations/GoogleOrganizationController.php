<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleOrganizationSyncService;
use Illuminate\Http\Request;

class GoogleOrganizationController extends Controller
{
    public function __construct(
        protected GoogleOrganizationSyncService $syncService
    ) {}

    /**
     * Sincroniza a organização do Google Workspace
     */
    public function sync(Request $request, string $integrationId)
    {
        $organizationId = session('active_organization_id');
        
        $integration = Integration::where('organization_id', $organizationId)
            ->where('id', $integrationId)
            ->where('provider', 'google_workspace')
            ->firstOrFail();

        try {
            $this->syncService->sync($integration);
            return back()->with('success', 'Organização sincronizada com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao sincronizar organização: ' . $e->getMessage());
        }
    }
}
