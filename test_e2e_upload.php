<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Force boot the app to get full services
$kernel->handle(Illuminate\Http\Request::capture());

use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Services\MessageService;
use App\Domain\AI\Api\Services\AIAttachmentsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

$organization = Organization::first();
$user = User::first();
$conversation = Conversation::firstOrCreate([
    'organization_id' => $organization->id,
    'user_id' => $user->id,
], [
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'title' => 'Test',
    'status' => 'active'
]);

$fileContent = 'dummy pdf content for E2E test';
file_put_contents(__DIR__.'/dummy_e2e.pdf', $fileContent);
$file = new UploadedFile(__DIR__.'/dummy_e2e.pdf', 'dummy_e2e.pdf', 'application/pdf', null, true);

$messageService = new MessageService();
$message = $messageService->addUserMessage($conversation, 'Test msg', [$file]);

$attachment = $message->attachments()->first();

echo "Attachment UUID: " . $attachment->uuid . "\n";
echo "Storage Path: " . $attachment->storage_path . "\n";
echo "Exists on disk? " . (Storage::disk('chat-attachments')->exists($attachment->storage_path) ? 'Yes' : 'No') . "\n";

$downloadService = new AIAttachmentsService();
try {
    $response = $downloadService->download($organization, $user, $attachment->uuid);
    echo "Download success!\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
}

