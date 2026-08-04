<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organizations\Actions\UpdateOrganizationAction;
use App\Domain\Organizations\Models\CompanyVerification;
use App\Domain\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $organizationId = session('active_organization_id');
        $organization   = Organization::with('verification')->find($organizationId);

        return Inertia::render('Settings/Index', [
            'organization' => $organization,
            'verification' => $organization?->verification,
        ]);
    }

    public function update(Request $request, UpdateOrganizationAction $action)
    {
        $organizationId = session('active_organization_id');
        $organization   = Organization::find($organizationId);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'cnpj'    => [
                'nullable',
                'string',
                'max:18',
                Rule::unique('organizations', 'cnpj')->ignore($organization->id)->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'industry' => ['nullable', 'string', 'max:255'],
            'logo'    => ['nullable', 'image', 'max:10240'],
        ]);

        $action->execute($organization, $validated, $request->file('logo'));

        return back()->with('success', 'Configurações atualizadas com sucesso.');
    }

    // ─── Company Verification ──────────────────────────────────

    public function storeVerification(Request $request)
    {
        $organizationId = session('active_organization_id');

        $validated = $request->validate([
            'company_name'          => ['required', 'string', 'max:255'],
            'trade_name'            => ['nullable', 'string', 'max:255'],
            'cnpj'                  => ['required', 'string', 'max:18'],
            'website'               => ['nullable', 'url', 'max:255'],
            'linkedin'              => ['nullable', 'string', 'max:255'],
            'responsible_name'      => ['required', 'string', 'max:255'],
            'responsible_position'  => ['required', 'string', 'max:255'],
            'corporate_email'       => ['required', 'email', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:20'],
            'document_type'         => ['required', Rule::in(['cnpj_card', 'social_contract', 'ccmei'])],
            'document'              => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'declaration_accepted'  => ['required', 'accepted'],
        ]);

        $existing = CompanyVerification::where('organization_id', $organizationId)->first();

        // Bloquear reenvio se já verificado
        if ($existing && $existing->isVerified()) {
            return back()->with('error', 'Esta empresa já está verificada.');
        }

        // Armazenar documento PDF
        $docFile = $request->file('document');
        $docPath = $docFile->store('verification-docs', 'private');

        $verificationData = [
            'organization_id'       => $organizationId,
            'company_name'          => $validated['company_name'],
            'trade_name'            => $validated['trade_name'] ?? null,
            'cnpj'                  => $validated['cnpj'],
            'website'               => $validated['website'] ?? null,
            'linkedin'              => $validated['linkedin'] ?? null,
            'responsible_name'      => $validated['responsible_name'],
            'responsible_position'  => $validated['responsible_position'],
            'corporate_email'       => $validated['corporate_email'],
            'phone'                 => $validated['phone'] ?? null,
            'document_type'         => $validated['document_type'],
            'document_path'         => $docPath,
            'document_original_name' => $docFile->getClientOriginalName(),
            'declaration_accepted'  => true,
            'verification_status'   => 'under_review',
            'submitted_at'          => now(),
        ];

        if ($existing && $existing->isRejected()) {
            // Reenvio após reprovação: atualiza o registro
            $existing->update(array_merge($verificationData, ['review_notes' => null]));
        } else {
            CompanyVerification::create($verificationData);
        }

        return back()->with('success', 'Documentos enviados com sucesso. Aguarde a análise da Sacratech.');
    }
}
