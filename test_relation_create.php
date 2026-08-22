<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;
use Illuminate\Support\Str;

$organization = Organization::first();
$user = User::first();
$conversation = Conversation::firstOrCreate([
    'organization_id' => $organization->id,
    'user_id' => $user->id,
], [
    'uuid' => (string) Str::uuid(),
    'title' => 'Test',
    'status' => 'active'
]);

$message = $conversation->messages()->create([
    'role' => 'user',
    'content' => 'Test'
]);

$uuid = (string) Str::uuid();
echo "Generated UUID: " . $uuid . "\n";

$attachment = $message->attachments()->create([
    'uuid' => $uuid,
    'organization_id' => $organization->id,
    'conversation_id' => $conversation->id,
    'user_id' => $user->id,
    'original_name' => 'test.pdf',
    'storage_path' => 'path/to/test.pdf',
    'status' => 'staged'
]);

echo "Attachment Model UUID: " . $attachment->uuid . "\n";

$fromDb = $message->attachments()->first();
echo "From DB UUID: " . $fromDb->uuid . "\n";
