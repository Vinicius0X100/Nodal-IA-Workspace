<?php

use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;

$org = Organization::create(['name' => 'Test Org']);
$integration = Integration::create([
    'organization_id' => $org->id,
    'provider' => 'google',
    'access_token' => 'dummy',
    'status' => 'active',
    'display_name' => 'Google Workspace',
]);

$group = Group::create([
    'organization_id' => $org->id,
    'integration_id' => $integration->id,
    'name' => 'Test Group',
    'email' => 'test@example.com',
    'external_id' => '12345',
]);

$owner = User::create([
    'name' => 'Owner User',
    'email' => 'owner@example.com',
    'password' => bcrypt('password'),
]);
$owner->organizations()->sync([$org->id => ['is_owner' => true, 'joined_at' => now()]]);

$memberEmails = ['owner@example.com'];
$userIds = [];
foreach ($memberEmails as $email) {
    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'status' => 'active',
        ]
    );
    
    $user->organizations()->syncWithoutDetaching([
        $org->id => ['joined_at' => now()]
    ]);
    
    $userIds[] = $user->id;
}

$group->users()->sync($userIds);

echo "Group users count: " . $group->users()->count() . "\n";
echo "Group users ids: " . implode(', ', $group->users()->pluck('users.id')->toArray()) . "\n";
echo "Done.\n";
