<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Organizations\Models\Organization;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Integrations\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;

// Pega a primeira integração ativa do Google Workspace
$integration = Integration::where('provider', 'google_workspace')->where('status', 'connected')->first();
if (!$integration) {
    die("Nenhuma integração conectada encontrada.\n");
}

$organization = $integration->organization;
$identity = ExternalIdentity::where('integration_id', $integration->id)->first();
if (!$identity) {
    die("Nenhuma ExternalIdentity encontrada.\n");
}

$service = app(GoogleCalendarService::class);

echo "Testando com organização: {$organization->name}, identidade: {$identity->primary_email}\n";

$eventData = [
    'summary' => 'Teste via Script Nodal',
    'start' => date('c', strtotime('+1 hour')),
    'end' => date('c', strtotime('+2 hours')),
    'time_zone' => 'America/Sao_Paulo',
    // SEM attendees
    // SEM create_meeting
];

try {
    $result = $service->createEvent($organization, $integration, $eventData, null, null, $identity);
    echo "Sucesso!\n";
    print_r($result);
} catch (\Exception $e) {
    echo "Falhou com exceção: " . get_class($e) . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
}

// Ler os últimos logs
echo "\n--- ÚLTIMOS LOGS (storage/logs/laravel.log) ---\n";
system("tail -n 20 storage/logs/laravel.log");
