<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$org = \App\Domain\Organizations\Models\Organization::first();
if (!$org) {
    $org = \App\Domain\Organizations\Models\Organization::create(['name' => 'E2E Org', 'slug' => 'e2e-org']);
}

$user = \App\Domain\Identity\Models\User::first();
if (!$user) {
    $user = \App\Domain\Identity\Models\User::create(['name' => 'E2E User', 'email' => 'e2e@example.com', 'password' => bcrypt('password'), 'organization_id' => $org->id]);
}

$request = Illuminate\Http\Request::create('/api/ai/artifacts/spreadsheets/drafts', 'POST', [
    'title' => 'Orçamento Revisável',
    'sheets' => [
        [
            'title' => 'Planilha1',
            'updates' => [
                [
                    'range' => 'A1:D3',
                    'values' => [
                        ["Produto", "Quantidade", "Valor Unitário", "Total"],
                        ["Notebook", 3, 5000, "=B2*C2"],
                        ["Monitor", 5, 1600, "=B3*C3"]
                    ]
                ]
            ]
        ]
    ]
]);
$request->headers->set('X-Organization-UUID', $org->uuid);
$request->headers->set('X-User-UUID', $user->uuid);

$response = app()->handle($request);
echo "POST STATUS: " . $response->getStatusCode() . "\n";
$content = json_decode($response->getContent(), true);
$uuid = $content['data']['artifact_uuid'] ?? null;

if ($uuid) {
    echo "UUID: $uuid\n";
    session()->put('active_organization_id', $org->id);
    
    // Simulate auth user login for getActiveOrganizationId check
    auth()->login($user);
    
    $req2 = Illuminate\Http\Request::create("/artifacts/$uuid/spreadsheet", 'GET');
    $req2->setLaravelSession(session()); // attach session
    $res2 = app()->handle($req2);
    echo "GET STATUS: " . $res2->getStatusCode() . "\n";
    if ($res2->getStatusCode() === 200) {
        $data = json_decode($res2->getContent(), true)['data'];
        echo "ACTIVE SHEET: " . $data['active_sheet']['title'] . "\n";
        echo "CELLS COUNT: " . count($data['viewport']['cells']) . "\n";
        echo "CELL A1: " . $data['viewport']['cells'][0]['value'] . "\n";
    } else {
        echo $res2->getContent() . "\n";
    }
}
