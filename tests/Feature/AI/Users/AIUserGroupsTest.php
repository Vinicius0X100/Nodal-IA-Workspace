<?php

namespace Tests\Feature\AI\Users;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIUserGroupsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private User $targetUser;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'active' => true,
        ]);

        // Create the user making the request
        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        // Create the target user we will query
        $this->targetUser = User::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->targetUser->id, ['is_owner' => false]);

        // Create a role and attach to the active user
        $this->role = Role::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Role',
            'slug' => 'test-role',
        ]);
        $this->user->roles()->attach($this->role->id, ['organization_id' => $this->organization->id]);

        // Create the necessary permissions
        Permission::firstOrCreate(['slug' => 'directory.groups.read'], ['name' => 'Read Groups', 'group' => 'Directory']);
        Permission::firstOrCreate(['slug' => 'directory.users.read'], ['name' => 'Read Users', 'group' => 'Directory']);
    }

    private function grantPermissions(array $slugs): void
    {
        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id')->toArray();
        $this->role->permissions()->sync($permissionIds);
    }

    private function actAsAI(User $user, Organization $organization)
    {
        config(['services.ai_gateway.token' => 'test-token']);
        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-User-UUID' => $user->uuid,
            'X-Organization-UUID' => $organization->uuid,
        ]);
    }

    public function test_authorized_user_can_read_user_groups()
    {
        $this->grantPermissions(['directory.users.read', 'directory.groups.read']);

        // Create groups and attach to target user
        $group1 = Group::create([
            'organization_id' => $this->organization->id,
            'name' => 'Group 1',
            'email' => 'group1@example.com'
        ]);
        $group2 = Group::create([
            'organization_id' => $this->organization->id,
            'name' => 'Group 2'
        ]);

        $this->targetUser->groups()->attach([$group1->id, $group2->id]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$this->targetUser->uuid}/groups");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'uuid' => $this->targetUser->uuid,
                        'name' => $this->targetUser->name,
                        'email' => $this->targetUser->email,
                    ],
                    'total' => 2
                ]
            ]);

        $this->assertCount(2, $response->json('data.groups'));
        $this->assertEquals($group1->uuid, $response->json('data.groups.0.uuid'));
        
        // Assert sensitive data is NOT returned
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('id', $response->json('data.user'));
        $this->assertArrayNotHasKey('id', $response->json('data.groups.0'));

        // Assert audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai_read_user_groups',
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_without_groups_returns_empty_array()
    {
        $this->grantPermissions(['directory.users.read', 'directory.groups.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$this->targetUser->uuid}/groups");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'groups' => [],
                    'total' => 0
                ]
            ]);
    }

    public function test_user_without_users_permission_receives_403()
    {
        $this->grantPermissions(['directory.groups.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$this->targetUser->uuid}/groups");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCESS_DENIED'
            ]);
    }

    public function test_user_without_groups_permission_receives_403()
    {
        $this->grantPermissions(['directory.users.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$this->targetUser->uuid}/groups");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCESS_DENIED'
            ]);
    }

    public function test_accessing_user_from_different_organization_returns_404()
    {
        $this->grantPermissions(['directory.users.read', 'directory.groups.read']);

        $otherOrganization = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'active' => true,
        ]);
        
        $otherUser = User::create([
            'name' => 'Other Org User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $otherOrganization->users()->attach($otherUser->id, ['is_owner' => false]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$otherUser->uuid}/groups");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'USER_NOT_FOUND'
            ]);
    }

    public function test_inexistent_uuid_returns_404()
    {
        $this->grantPermissions(['directory.users.read', 'directory.groups.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/" . Str::uuid() . "/groups");

        $response->assertStatus(404);
    }

    public function test_owner_bypasses_permissions()
    {
        // Make the requesting user the owner
        $this->organization->users()->updateExistingPivot($this->user->id, ['is_owner' => true]);
        
        // No permissions granted to the role
        $this->role->permissions()->detach();

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/users/{$this->targetUser->uuid}/groups");

        // Should succeed because owner bypasses AuthorizationService checks
        $response->assertStatus(200);
    }
}
