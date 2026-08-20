<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Integrations\Models\IntegrationWebhook;
use App\Domain\Resources\Jobs\SyncProviderResourcesJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $channelId = $request->header('x-goog-channel-id');
        $resourceState = $request->header('x-goog-resource-state');
        $resourceId = $request->header('x-goog-resource-id');

        // Google requires a 200 OK response as quickly as possible.
        // We will process the logic and if anything fails, we just log it and still return 200.
        
        if (!$channelId) {
            Log::warning('Google Drive Webhook received without x-goog-channel-id header.');
            return response()->json(['status' => 'ok']);
        }

        try {
            $webhook = IntegrationWebhook::where('channel_id', $channelId)->with('integration')->first();

            if (!$webhook) {
                Log::warning("Google Drive Webhook received for unknown channel_id: {$channelId}");
                return response()->json(['status' => 'ok']);
            }

            // Sync state is the initial confirmation when we register the channel.
            if ($resourceState === 'sync') {
                Log::info("Google Drive Webhook synced for channel_id: {$channelId}, resource_id: {$resourceId}");
                $webhook->update(['resource_id' => $resourceId, 'state' => 'active']);
                return response()->json(['status' => 'ok']);
            }

            // For any changes (add, update, trash, etc), we dispatch the Sync Job
            if ($webhook->integration) {
                Log::info("Google Drive Webhook trigger ({$resourceState}) for integration: {$webhook->integration->id}");
                SyncProviderResourcesJob::dispatch($webhook->integration);
            }
        } catch (\Exception $e) {
            Log::error('Error processing Google Drive Webhook: ' . $e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }
}
