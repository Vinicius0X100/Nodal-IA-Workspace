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
