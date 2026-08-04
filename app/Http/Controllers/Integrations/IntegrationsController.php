<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntegrationsController extends Controller
{
    /**
     * Marketplace de Integrações
     */
    public function index(Request $request)
    {
        return Inertia::render('Integrations/Index');
    }

    /**
     * Detalhes / Configuração do Google Workspace
     */
    public function googleWorkspace(Request $request)
    {
        return Inertia::render('Integrations/Providers/GoogleWorkspace', [
            'app_url' => config('app.url'),
        ]);
    }
}
