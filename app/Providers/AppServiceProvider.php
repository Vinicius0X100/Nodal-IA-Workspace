<?php

namespace App\Providers;

use App\Domain\Billing\Policies\BillingPolicy;
use App\Domain\Billing\Services\AICostCalculator;
use App\Domain\Billing\Services\AIUsageLimitService;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Billing\Services\BillingAlertService;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Artifacts\Repositories\SpreadsheetDraftRepositoryInterface::class,
            \App\Domain\Artifacts\Repositories\SpreadsheetDraftRepository::class
        );
        $this->app->bind(
            \App\Domain\AI\Contracts\AIProviderInterface::class,
            \App\Domain\AI\Providers\N8nProvider::class
        );

        // Artifact Provider Abstraction — Phase 4
        $this->app->bind(
            \App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class,
            \App\Domain\Artifacts\Providers\SpreadsheetProviderResolver::class
        );
        $this->app->bind(
            \App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface::class,
            \App\Domain\Artifacts\Providers\Materialization\SpreadsheetMaterializationReader::class
        );
        $this->app->bind(
            \App\Domain\Artifacts\Providers\Contracts\SpreadsheetBatchPlannerInterface::class,
            \App\Domain\Artifacts\Providers\Materialization\SpreadsheetBatchPlanner::class
        );

        // Billing domain — singleton services
        $this->app->singleton(AICostCalculator::class);
        $this->app->singleton(BillingSubscriptionService::class);
        $this->app->singleton(BillingAlertService::class);
        $this->app->singleton(AIUsageLimitService::class);
        $this->app->singleton(AIUsageService::class);

        $this->app->bind(
            \App\Domain\Billing\Contracts\PaymentProviderInterface::class,
            \App\Domain\Billing\Providers\AsaasPaymentProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Mail::extend('brevo', function (array $config = []) {
            return new \Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport(
                config('mail.mailers.brevo.key')
            );
        });

        \App\Domain\Billing\Models\OrganizationSubscription::observe(\App\Domain\Billing\Observers\OrganizationSubscriptionObserver::class);

        // Registrar Gates para Billing (já que a policy é de domínio e não está mapeada 1:1 para um Model Eloquent padrão aqui)
        Gate::define('billing.view', [BillingPolicy::class, 'view']);
        Gate::define('billing.manage', [BillingPolicy::class, 'manage']);
        Gate::define('billing.alerts.manage', [BillingPolicy::class, 'manageAlerts']);
        Gate::define('billing.invoices.view', [BillingPolicy::class, 'viewInvoices']);
        Gate::define('billing.invoices.manage', [BillingPolicy::class, 'manageInvoices']);
    }
}

