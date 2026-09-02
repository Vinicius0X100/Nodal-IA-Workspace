<?php

namespace App\Providers;

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
    }
}
