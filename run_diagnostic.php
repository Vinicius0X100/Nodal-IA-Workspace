<?php

use App\Domain\Directory\Models\Group;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;

$group = Group::whereNotNull('integration_id')->whereNotNull('external_id')->first();
$output = [];

if (!$group) {
    $output['error'] = 'No group with integration_id found in dev database.';
} else {
    $integration = Integration::find($group->integration_id);
    
    $output['group'] = [
        'uuid' => $group->uuid,
        'external_id' => $group->external_id,
        'email' => $group->email,
        'integration_id' => $group->integration_id,
    ];
    
    if (!$integration) {
        $output['error'] = 'Integration not found for the group.';
    } else {
        $output['integration'] = [
            'scopes' => $integration->scope, // Depending on how it's casted
        ];
        
        $token = $integration->access_token;
        
        $response = Http::withToken($token)
            ->get("https://admin.googleapis.com/admin/directory/v1/groups/{$group->external_id}/members", [
                'maxResults' => 200,
            ]);
            
        $output['api_response'] = [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }
}

// Check Integration logs
$logs = IntegrationLog::whereIn('event', ['sync_group_members', 'sync_group_members_error'])
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['event', 'status', 'message', 'created_at']);

$output['recent_logs'] = $logs->toArray();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
