<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Domain\Identity\Models\User;

$user = new User(['id' => 123]);

$request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['foo' => 'bar']));
try {
    $request->merge(['_active_user' => $user]);
    $resolvedUser = $request->get('_active_user');
    echo "Merged user ID: " . (is_object($resolvedUser) ? $resolvedUser->id : 'null') . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
