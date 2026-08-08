<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Sincroniza grupos importados do Google Workspace periodicamente
Schedule::command('integrations:sync-google-groups')->dailyAt('02:00');
