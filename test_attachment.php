<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Services\MessageService;
use App\Domain\AI\Api\Services\AIAttachmentsService;
use Illuminate\Http\UploadedFile;

// Setup a dummy organization, user, and conversation
$organization = Organization::first();
if (!$organization) {
    echo "No organization found\n";
    exit;
}

$user = User::first();
if (!$user) {
    echo "No user found\n";
    exit;
}

$conversation = Conversation::firstOrCreate([
    'organization_id' => $organization->id,
    'user_id' => $user->id,
], [
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'title' => 'Test',
    'status' => 'active'
]);

// Create dummy file
file_put_contents(__DIR__.'/dummy.pdf', 'dummy pdf content');
$file = new UploadedFile(__DIR__.'/dummy.pdf', 'dummy.pdf', 'application/pdf', null, true);

// Use MessageService to store
$messageService = new MessageService();
$message = $messageService->addUserMessage($conversation, 'Test msg', [$file]);

$metadata = $message->metadata_json;
$attachmentUuid = $metadata['attachments'][0]['attachment_uuid'];

echo "Attachment UUID: " . $attachmentUuid . "\n";

// Now test download service
$downloadService = new AIAttachmentsService();
try {
    $response = $downloadService->download($organization, $user, $attachmentUuid);
    echo "Download success!\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
}

