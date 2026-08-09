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

class AIGroupsWithMembersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private Group $groupWithMembers;
    private Group $groupEmpty;
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

        // Create a user and attach to organization
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

        // Create permissions
        Permission::firstOrCreate(['slug' => 'directory.groups.read'], ['name' => 'Read Groups', 'group' => 'Directory']);
        Permission::firstOrCreate(['slug' => 'directory.users.read'], ['name' => 'Read Users', 'group' => 'Directory']);

        // Create groups
        $this->groupWithMembers = Group::create([
            'organization_id' => $this->organization->id,
            'name' => 'Group With Members'
        ]);
        
        $this->groupEmpty = Group::create([
            'organization_id' => $this->organization->id,
            'name' => 'Empty Group'
        ]);
    }

    private function grantPermissions(array $slugs): void
    {
        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id')->toArray();
        $this->role->permissions()->sync($permissionIds);
    }

    private function actAsAI(User $user, Organization $organization)
    {
        $this->withMiddleware(function ($request, $next) use ($user, $organization) {
            $request->attributes->set('_active_user', $user);
            $request->attributes->set('_active_organization', $organization);
            return $next($request);
        });
        
        return $this;
    }

    public function test_authorized_user_can_read_groups_with_members()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        // Add members
        $member1 = User::create([
            'name' => 'Member 1',
            'email' => 'member1@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->groupWithMembers->users()->attach([$member1->id]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/with-members");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Assert length is 2 (both empty and non-empty are returned)
        $this->assertCount(2, $data);
        
        // Find group with members
        $groupWithMembersData = collect($data)->firstWhere('uuid', $this->groupWithMembers->uuid);
        $this->assertEquals(1, $groupWithMembersData['members_count']);
        $this->assertCount(1, $groupWithMembersData['members']);
        
        // Find empty group
        $emptyGroupData = collect($data)->firstWhere('uuid', $this->groupEmpty->uuid);
        $this->assertEquals(0, $emptyGroupData['members_count']);
        $this->assertCount(0, $emptyGroupData['members']);

        // Assert audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai_read_all_groups_with_members',
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_filter_only_has_members()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        // Add members
        $member1 = User::create([
            'name' => 'Member 1',
            'email' => 'member1@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->groupWithMembers->users()->attach([$member1->id]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/with-members?has_members=true");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Assert length is 1 (only the group with members is returned)
        $this->assertCount(1, $data);
        $this->assertEquals($this->groupWithMembers->uuid, $data[0]['uuid']);
    }

    public function test_user_without_permission_receives_403()
    {
        // Only one permission, missing directory.users.read
        $this->grantPermissions(['directory.groups.read']);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/with-members");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCESS_DENIED'
            ]);
    }

    public function test_groups_from_different_organization_are_not_returned()
    {
        $this->grantPermissions(['directory.groups.read', 'directory.users.read']);

        $otherOrganization = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'active' => true,
        ]);
        Group::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Group'
        ]);

        $response = $this->actAsAI($this->user, $this->organization)
            ->getJson("/api/ai/groups/with-members");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Assert length is 2 (only groups from this organization)
        $this->assertCount(2, $data);
    }
}
