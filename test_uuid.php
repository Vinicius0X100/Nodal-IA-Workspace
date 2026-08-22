<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Domain\AI\Models\MessageAttachment;
use Illuminate\Support\Str;

$uuid = (string) Str::uuid();
echo "Generated UUID: " . $uuid . "\n";

$model = new MessageAttachment([
    'uuid' => $uuid,
    'original_name' => 'test'
]);

echo "Model UUID before save: " . $model->uuid . "\n";

// Trigger creating event
$app['events']->dispatch('eloquent.creating: ' . MessageAttachment::class, [$model]);

echo "Model UUID after creating event: " . $model->uuid . "\n";
