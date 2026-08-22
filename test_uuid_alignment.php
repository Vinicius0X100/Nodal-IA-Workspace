<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Services\MessageService;
use App\Domain\AI\Providers\N8nProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

$organization = Organization::first();
$user = User::first();
$conversation = Conversation::firstOrCreate([
    'organization_id' => $organization->id,
    'user_id' => $user->id,
], [
    'uuid' => (string) Str::uuid(),
    'title' => 'Test UUID Alignment',
    'status' => 'active'
]);

$fileContent = 'dummy pdf content for E2E UUID test';
file_put_contents(__DIR__.'/dummy_e2e.pdf', $fileContent);
$file = new UploadedFile(__DIR__.'/dummy_e2e.pdf', 'dummy_e2e.pdf', 'application/pdf', null, true);

$messageService = app(MessageService::class);
$message = $messageService->addUserMessage($conversation, 'Testing UUID Alignment', [$file]);

$attachmentFromDb = $message->attachments()->first();
$uuidFromDb = $attachmentFromDb->uuid;

$metadataAttachments = $message->metadata_json['attachments'] ?? [];
$uuidFromMetadata = $metadataAttachments[0]['attachment_uuid'] ?? 'missing';

$n8nProvider = app(N8nProvider::class);

// Mocar o Provider para capturar o payload antes de enviar
$reflection = new ReflectionClass(N8nProvider::class);
$method = $reflection->getMethod('chat');
// we just want to execute N8nProvider history logic. Let's do it manually since chat() sends http request.

$history = $conversation->messages()
    ->orderBy('created_at', 'asc')
    ->get()
    ->map(function ($msg) {
        $item = [
            'role' => $msg->role->value,
            'content' => $msg->content,
        ];

        $msg->load('attachments');

        if ($msg->attachments && $msg->attachments->isNotEmpty()) {
            $item['attachments'] = $msg->attachments->map(function ($att) {
                return [
                    'attachment_uuid' => $att->uuid,
                    'name' => $att->original_name,
                    'mime_type' => $att->mime_type,
                    'size' => $att->size,
                ];
            })->toArray();
        } elseif (!empty($msg->metadata_json['attachments'])) {
            $item['attachments'] = $msg->metadata_json['attachments'];
        }

        return $item;
    })
    ->toArray();

$latestMessage = end($history);
$uuidFromN8n = $latestMessage['attachments'][0]['attachment_uuid'] ?? 'missing';

echo "Resultados:\n";
echo "1. message_attachments.uuid (DB): " . $uuidFromDb . "\n";
echo "2. metadata_json attachment_uuid: " . $uuidFromMetadata . "\n";
echo "3. N8nProvider attachment_uuid: " . $uuidFromN8n . "\n";

if ($uuidFromDb === $uuidFromMetadata && $uuidFromDb === $uuidFromN8n) {
    echo "\nSUCESSO: Os 3 UUIDs são idênticos!\n";
} else {
    echo "\nFALHA: Divergência detectada.\n";
}

