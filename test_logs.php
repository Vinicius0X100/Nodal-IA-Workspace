<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = \App\Domain\Integrations\Models\IntegrationLog::latest()->limit(10)->get();
foreach($logs as $log) {
    echo "ID: " . $log->id . " | Event: " . $log->event . " | Status: " . $log->status . " | Msg: " . $log->message . "\n";
}
