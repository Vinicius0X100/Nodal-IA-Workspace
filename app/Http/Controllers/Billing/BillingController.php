<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\AiUsageDailyRollup;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingAlertRecipient;
use App\Domain\Billing\Services\AIUsageLimitService;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingSubscriptionService $subscriptionService,
        private readonly AIUsageLimitService        $limitService,
    ) {}

    /** GET /settings/billing — Visão Geral */
    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $usageState   = $this->limitService->getUsageState($organization);
        $subscription = $this->subscriptionService->activeSubscription($organization);

        // Calcular projeção de consumo até fim do período
        $projection = $this->calculateProjection($organization, $usageState);

        return Inertia::render('Settings/Billing/Index', [
            'usage_state'  => $usageState,
            'subscription' => $subscription ? [
                'id'                   => $subscription->id,
                'status'               => $subscription->status?->value,
                'current_period_start' => $subscription->current_period_start?->toDateString(),
                'current_period_end'   => $subscription->current_period_end?->toDateString(),
                'postpaid_enabled'     => $subscription->postpaid_enabled,
                'postpaid_limit_brl'   => $subscription->postpaid_limit_cents !== null
                    ? $subscription->postpaid_limit_cents / 100 : null,
                'plan' => $subscription->plan ? [
                    'code'                 => $subscription->plan->code,
                    'name'                 => $subscription->plan->name,
                    'monthly_price_brl'    => $subscription->plan->monthlyPriceBrl(),
                    'included_ai_credits'  => $subscription->plan->included_ai_credits,
                    'features'             => $subscription->plan->features_json,
                ] : null,
            ] : null,
            'projection'   => $projection,
        ]);
    }

    /** GET /settings/billing/usage — Uso de IA */
    public function usage(Request $request)
    {
        $organization = $this->organization($request);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());

        // Rollups diários para gráfico temporal
        $dailyRollups = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($request->input('provider'), fn ($q, $v) => $q->where('provider', $v))
            ->when($request->input('model'), fn ($q, $v) => $q->where('model', $v))
            ->when($request->input('operation'), fn ($q, $v) => $q->where('operation', $v))
            ->selectRaw('
                date,
                SUM(credits_used) as credits_used,
                SUM(provider_cost_brl) as provider_cost_brl,
                SUM(prompt_tokens) as prompt_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(thinking_tokens) as thinking_tokens,
                SUM(requests_count) as requests_count
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Totais do período
        $totals = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(credits_used) as total_credits,
                SUM(provider_cost_brl) as total_cost_brl,
                SUM(prompt_tokens) as total_prompt_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(thinking_tokens) as total_thinking_tokens,
                SUM(requests_count) as total_requests
            ')
            ->first();

        // Distribuição por modelo
        $byModel = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('model, provider, SUM(credits_used) as credits_used, SUM(requests_count) as requests_count')
            ->groupBy('provider', 'model')
            ->orderByDesc('credits_used')
            ->get();

        // Distribuição por categoria
        $byCategory = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('billing_category, SUM(credits_used) as credits_used, SUM(requests_count) as requests_count')
            ->groupBy('billing_category')
            ->orderByDesc('credits_used')
            ->get();

        return Inertia::render('Settings/Billing/Usage', [
            'daily_rollups' => $dailyRollups,
            'totals'        => $totals,
            'by_model'      => $byModel,
            'by_category'   => $byCategory,
            'filters'       => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'provider'   => $request->input('provider'),
                'model'      => $request->input('model'),
                'operation'  => $request->input('operation'),
            ],
        ]);
    }

    /** GET /settings/billing/users — Consumo por Usuário */
    public function users(Request $request)
    {
        $organization = $this->organization($request);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());

        // Agregado por usuário
        $userUsage = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereNotNull('user_id')
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('user_id, SUM(credits_used) as credits_used, SUM(requests_count) as requests_count')
            ->groupBy('user_id')
            ->orderByDesc('credits_used')
            ->with('user:id,uuid,name,email,avatar')
            ->get()
            ->map(function ($row) use ($organization) {
                $totalOrgCredits = $row->credits_used; // simplificado
                return [
                    'user'          => $row->user ? [
                        'uuid'   => $row->user->uuid,
                        'name'   => $row->user->name,
                        'email'  => $row->user->email,
                        'avatar' => $row->user->avatar,
                    ] : null,
                    'credits_used'  => round($row->credits_used, 2),
                    'requests'      => $row->requests_count,
                ];
            });

        // Total da org para calcular %
        $orgTotal = AiUsageDailyRollup::where('organization_id', $organization->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('credits_used');

        // Adicionar percentual
        $userUsage = $userUsage->map(function ($row) use ($orgTotal) {
            $row['percentage'] = $orgTotal > 0 ? round(($row['credits_used'] / $orgTotal) * 100, 1) : 0;
            return $row;
        });

        return Inertia::render('Settings/Billing/Users', [
            'user_usage' => $userUsage,
            'org_total'  => round($orgTotal, 2),
            'filters'    => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }

    /** GET /settings/billing/alerts — Alertas e Limites */
    public function alerts(Request $request)
    {
        $organization = $this->organization($request);
        $subscription = $this->subscriptionService->activeSubscription($organization);

        $recipients = BillingAlertRecipient::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->with(['user:id,uuid,name,email', 'group:id,uuid,name'])
            ->get();

        $users = \App\Domain\Identity\Models\User::whereHas('organizations', function($q) use ($organization) {
            $q->where('organization_id', $organization->id);
        })->select('id', 'uuid', 'name', 'email')->get();

        $groups = \App\Domain\Directory\Models\Group::where('organization_id', $organization->id)
            ->select('id', 'uuid', 'name')->get();

        return Inertia::render('Settings/Billing/Alerts', [
            'recipients'    => $recipients,
            'users'         => $users,
            'groups'        => $groups,
            'postpaid'      => [
                'enabled'     => $subscription?->postpaid_enabled ?? false,
                'limit_brl'   => $subscription?->postpaid_limit_cents !== null
                    ? $subscription->postpaid_limit_cents / 100 : null,
            ],
            'thresholds'    => \App\Domain\Billing\Enums\AlertType::creditUsageThresholds(),
        ]);
    }

    /** POST /settings/billing/alerts/recipients */
    public function updateAlertRecipients(Request $request)
    {
        $organization = $this->organization($request);
        \Illuminate\Support\Facades\Gate::authorize('billing.alerts.manage', $organization);

        $validated = $request->validate([
            'recipients' => ['present', 'array'],
            'recipients.*.recipient_type' => ['required', 'in:user,group'],
            'recipients.*.recipient_uuid' => ['required', 'uuid'],
            'recipients.*.usage_alerts' => ['required', 'boolean'],
            'recipients.*.invoice_alerts' => ['required', 'boolean'],
            'recipients.*.payment_alerts' => ['required', 'boolean'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($organization, $validated) {
            BillingAlertRecipient::where('organization_id', $organization->id)->delete();

            foreach ($validated['recipients'] as $recipient) {
                $recipientId = null;
                if ($recipient['recipient_type'] === 'user') {
                    $user = \App\Domain\Identity\Models\User::where('uuid', $recipient['recipient_uuid'])->firstOrFail();
                    abort_if(!$organization->hasMember($user), 403, 'Usuário não pertence à organização.');
                    $recipientId = $user->id;
                } else {
                    $group = \App\Domain\Directory\Models\Group::where('uuid', $recipient['recipient_uuid'])
                        ->where('organization_id', $organization->id)->firstOrFail();
                    $recipientId = $group->id;
                }

                BillingAlertRecipient::create([
                    'organization_id' => $organization->id,
                    'recipient_type'  => $recipient['recipient_type'],
                    'user_id'         => $recipient['recipient_type'] === 'user' ? $recipientId : null,
                    'group_id'        => $recipient['recipient_type'] === 'group' ? $recipientId : null,
                    'is_active'       => true,
                    'usage_alerts'    => $recipient['usage_alerts'],
                    'invoice_alerts'  => $recipient['invoice_alerts'],
                    'payment_alerts'  => $recipient['payment_alerts'],
                ]);
            }
        });

        // Simula auditoria (Organization::auditLogs se existir)
        activity()
            ->performedOn($organization)
            ->causedBy($request->user())
            ->log('billing_alert_recipients_updated');

        return redirect()->back()->with('success', 'Destinatários de alertas atualizados.');
    }

    /** PUT /settings/billing/postpaid */
    public function updatePostpaidSettings(Request $request)
    {
        $organization = $this->organization($request);
        \Illuminate\Support\Facades\Gate::authorize('billing.alerts.manage', $organization);

        $validated = $request->validate([
            'postpaid_enabled'   => ['required', 'boolean'],
            'postpaid_limit_brl' => ['nullable', 'numeric', 'min:0'],
        ]);

        $subscription = $this->subscriptionService->activeSubscription($organization);
        
        abort_if(!$subscription, 400, 'Organização não possui plano ativo.');

        $subscription->update([
            'postpaid_enabled'     => $validated['postpaid_enabled'],
            'postpaid_limit_cents' => $validated['postpaid_limit_brl'] !== null ? intval($validated['postpaid_limit_brl'] * 100) : null,
        ]);

        activity()
            ->performedOn($organization)
            ->causedBy($request->user())
            ->log('billing_postpaid_settings_updated');

        return redirect()->back()->with('success', 'Configurações de uso adicional atualizadas.');
    }

    /** GET /settings/billing/invoices — Faturas */
    public function invoices(Request $request)
    {
        $organization = $this->organization($request);

        $invoices = BillingInvoice::where('organization_id', $organization->id)
            ->with('subscription.plan:id,code,name')
            ->orderByDesc('period_start')
            ->paginate(12);

        return Inertia::render('Settings/Billing/Invoices', [
            'invoices' => $invoices,
        ]);
    }

    private function organization(Request $request): Organization
    {
        $orgId = session('active_organization_id');
        return Organization::findOrFail($orgId);
    }

    private function calculateProjection(Organization $organization, array $usageState): array
    {
        $periodStart = $usageState['period_start'] ? Carbon::parse($usageState['period_start']) : now()->startOfMonth();
        $periodEnd   = $usageState['period_end']   ? Carbon::parse($usageState['period_end'])   : now()->endOfMonth();

        // Calculate days cleanly treating start/end days explicitly
        $daysTotal   = max((int) $periodStart->startOfDay()->diffInDays($periodEnd->endOfDay(), false) + 1, 1);
        $daysPassed  = max((int) $periodStart->startOfDay()->diffInDays(now()->endOfDay(), false) + 1, 1);
        $daysLeft    = max((int) now()->startOfDay()->diffInDays($periodEnd->endOfDay(), false), 0);

        // Se passamos do período, ajustar dias para não explodir a projeção
        $daysPassed  = min($daysPassed, $daysTotal);

        $creditsUsed = $usageState['credits_used'];
        $dailyRate   = $daysPassed > 0 ? $creditsUsed / $daysPassed : 0;
        $projected   = $dailyRate * $daysTotal;

        $projectedOverage = max($projected - $usageState['included_credits'], 0);

        return [
            'projected_credits'      => round($projected, 0),
            'projected_overage'      => round($projectedOverage, 0),
            'days_left'              => $daysLeft,
            'days_total'             => $daysTotal,
            'days_passed'            => $daysPassed,
            'daily_rate'             => round($dailyRate, 2),
        ];
    }
}
