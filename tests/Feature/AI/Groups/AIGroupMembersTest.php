<?php

namespace Tests\Feature\AI\Groups;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIGroupMembersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private Group $group;
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

        // Create a user and attach to organization (not owner)
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        // Create a role and attach to user
        $this->role = Role::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Role',
            'slug' => 'test-role',
        ]);
        $this->user->roles()->attach($this->role->id, ['organization_id' => $this->organization->id]);

        // Create the necessary permissions
        Permission::firstOrCreate(['slug' => 'directory.groups.read'], ['name' => 'Read Groups', 'group' => 'Directory']);
        Permission::firstOrCreate(['slug' => 'directory.users.read'], ['name' => 'Read Users', 'group' => 'Directory']);

        // Create a group in the organization
        $this->group = Group::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Group'
        ]);
    }

    private function grantPermissions(array $slugs): void
    {
        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id')->toArray();
        $this->role->permissions()->sync($permissionIds);
    }

    /**
     * Setup Request with Context Attributes.
     * We can use a small middleware in tests to inject the required attributes.
     */
    private function actAsAI(User $user, Organization $organization)
    {
        $this->withMiddleware(function ($request, $next) use ($user, $organization) {
            $request->attributes->set('_active_user', $user);
            $request->attributes->set('_active_organization', $organization);
            return $next($request);
        });
        
        return $this;
    }

    public function test_authorized_user_can_read_group_members()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        // Add members to group
        $member1 = User::create([
            'name' => 'Member 1',
            'email' => 'member1@example.com',
            'password' => bcrypt('password'),
        ]);
        $member2 = User::create([
            'name' => 'Member 2',
            'email' => 'member2@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->group->users()->attach([$member1->id, $member2->id]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/{$this->group->uuid}/members");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'group' => [
                        'uuid' => $this->group->uuid,
                        'name' => $this->group->name,
                    ],
                    'total' => 2
                ]
            ]);

        $this->assertCount(2, $response->json('data.members'));
        $this->assertEquals($member1->uuid, $response->json('data.members.0.uuid'));
        
        // Assert sensitive data is NOT returned
        $this->assertArrayNotHasKey('password', $response->json('data.members.0'));
        $this->assertArrayNotHasKey('id', $response->json('data.members.0'));

        // Assert audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai_read_group_members',
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'entity_type' => get_class($this->group),
        ]);
    }

    public function test_group_without_members_returns_empty_array()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/{$this->group->uuid}/members");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'members' => [],
                    'total' => 0
                ]
            ]);
    }

    public function test_user_without_permission_receives_403()
    {
        // Only one permission, missing directory.users.read
        $this->grantPermissions(['directory.groups.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/{$this->group->uuid}/members");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCESS_DENIED'
            ]);
    }

    public function test_accessing_group_from_different_organization_returns_404()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        $otherOrganization = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'active' => true,
        ]);
        $otherGroup = Group::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Group'
        ]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/{$otherGroup->uuid}/members");

        // We expect 404 because the query is scoped by the active organization
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'GROUP_NOT_FOUND'
            ]);
    }

    public function test_inexistent_uuid_returns_404()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/" . Str::uuid() . "/members");

        $response->assertStatus(404);
    }

    public function test_owner_bypasses_permissions()
    {
        // User is owner
        $this->organization->users()->updateExistingPivot($this->user->id, ['is_owner' => true]);
        
        // No permissions granted to the role
        $this->role->permissions()->detach();

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/{$this->group->uuid}/members");

        // Should succeed because owner bypasses AuthorizationService checks
        $response->assertStatus(200);
    }
}
