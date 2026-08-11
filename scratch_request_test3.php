<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Domain\Identity\Models\User;

$user = clone app(User::class);
$user->id = 123;

$request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['foo' => 'bar']));
try {
    $request->merge(['_active_user' => $user]);
    $resolvedUser = $request->get('_active_user');
    echo "Resolved user type: " . gettype($resolvedUser) . "\n";
    if (is_array($resolvedUser)) {
        echo "It's an array! ID = " . ($resolvedUser['id'] ?? 'none') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
