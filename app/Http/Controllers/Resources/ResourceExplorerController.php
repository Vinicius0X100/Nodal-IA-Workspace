<?php

namespace App\Http\Controllers\Resources;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Jobs\SyncProviderResourcesJob;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Services\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ResourceExplorerController extends Controller
{
    public function __construct(
        private SearchService $searchService
    ) {
    }

    public function index(Request $request)
    {
        $organizationId = session('active_organization_id');
        
        $filters = $request->only(['search', 'type', 'is_shared', 'provider']);
        
        // Paginação na busca
        $resources = $this->searchService->search($organizationId, $filters);
        
        // Dados para o Dashboard
        $baseQuery = IntegrationResource::whereHas('integration', function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId);
        });

        $totalResources = (clone $baseQuery)->count();
        $totalsByType = (clone $baseQuery)
            ->select('resource_type', DB::raw('count(*) as total'))
            ->groupBy('resource_type')
            ->pluck('total', 'resource_type');

        $lastSync = (clone $baseQuery)->max('last_synced_at');

        return Inertia::render('Resources/Index', [
            'resources' => $resources,
            'filters' => $filters,
            'dashboard' => [
                'total' => $totalResources,
                'folders' => $totalsByType['folder'] ?? 0,
                'documents' => $totalsByType['document'] ?? 0,
                'spreadsheets' => $totalsByType['spreadsheet'] ?? 0,
                'pdfs' => $totalsByType['pdf'] ?? 0,
                'calendars' => $totalsByType['calendar'] ?? 0,
                'last_sync' => $lastSync,
            ]
        ]);
    }

    public function sync(Request $request)
    {
        $organizationId = session('active_organization_id');
        
        // Busca todas as integrações da org que possuem resources
        $integrations = Integration::where('organization_id', $organizationId)
            ->whereIn('provider', ['google_workspace']) // Adicionar outros provedores suportados
            ->get();

        foreach ($integrations as $integration) {
            SyncProviderResourcesJob::dispatch($integration, $request->user()->id);
        }

        return redirect()->back()->with('success', 'Sincronização agendada com sucesso. Os recursos serão atualizados em breve.');
    }
}
