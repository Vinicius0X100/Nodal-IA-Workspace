<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

// Create a POST request with JSON
$request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['foo' => 'bar']));

// Middleware merges
$request->merge(['_active_organization' => (object)['id' => 123]]);

// Controller gets
$org = $request->get('_active_organization');
echo "Merged object ID: " . ($org->id ?? 'null') . "\n";

// Now what if the JSON payload contained _active_organization?
$request2 = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['_active_organization' => ['id' => 999]]));
$request2->merge(['_active_organization' => (object)['id' => 123]]);
$org2 = $request2->get('_active_organization');
echo "Merged object ID with collision: " . (is_object($org2) ? $org2->id : (is_array($org2) ? 'Array' : 'null')) . "\n";

