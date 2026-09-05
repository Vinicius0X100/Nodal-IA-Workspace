<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Sincroniza grupos importados do Google Workspace periodicamente
Schedule::command('integrations:sync-google-groups')->dailyAt('02:00');

// Fechamento de períodos de faturamento vencidos e emissão de faturas
Schedule::command('billing:close-periods')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();

