<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$integration = \App\Domain\Integrations\Models\Integration::where('provider', 'google_workspace')->whereNotNull('access_token')->first();
if (!$integration) {
    echo "No integration found\n";
    exit;
}

$group = \App\Domain\Directory\Models\Group::where('integration_id', $integration->id)->first();
if (!$group) {
    echo "No group found\n";
    exit;
}

echo "Syncing group: {$group->email}\n";
$service = new \App\Domain\Integrations\Services\GoogleDirectorySyncService();
$service->syncGroupMembers($integration, $group);

$members = $group->users()->get();
echo "Members linked: " . $members->count() . "\n";
