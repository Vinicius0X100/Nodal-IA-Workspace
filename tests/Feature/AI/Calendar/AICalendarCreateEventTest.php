<?php

namespace Tests\Feature\AI\Calendar;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationConfig;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Illuminate\Support\Str;

class AICalendarCreateEventTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $owner;
    private User         $user;
    private User         $otherUser;
    private Role         $roleSelf;
    private Role         $roleOrg;
    private Integration  $integration;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutExceptionHandling();

        $this->organization = Organization::create([
            'name'   => 'Test Corp',
            'slug'   => 'test-corp',
            'active' => true,
        ]);

        $this->owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->owner->id, ['is_owner' => true]);

        $this->user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $this->otherUser = User::create([
            'name'     => 'Other User',
            'email'    => 'other@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->otherUser->id, ['is_owner' => false]);

        $this->roleSelf = Role::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Self Role',
            'slug'            => 'self-role',
        ]);
        $this->user->roles()->attach($this->roleSelf->id, ['organization_id' => $this->organization->id]);

        $this->roleOrg = Role::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Org Role',
            'slug'            => 'org-role',
        ]);
        $this->otherUser->roles()->attach($this->roleOrg->id, ['organization_id' => $this->organization->id]);

        $perm = Permission::firstOrCreate(
            ['slug' => 'calendar.events.create'],
            ['name' => 'Criar eventos do calendário', 'group' => 'Calendário']
        );

        $this->integration = Integration::create([
            'organization_id'  => $this->organization->id,
            'provider'         => 'google_workspace',
            'display_name'     => 'Google Workspace',
            'status'           => 'connected',
            'access_token'     => 'fake-access-token',
            'refresh_token'    => 'fake-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        IntegrationConfig::create([
            'integration_id' => $this->integration->id,
            'client_id'      => 'test-client-id',
            'client_secret'  => 'test-client-secret',
            'delegation_credentials_json' => [
                'client_email' => 'test@test.com',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC8DxlwWbLxYnGG\n4Wa7BvXSyyS8yk9U7YNe0LkBtnVk8EHVBH5COPfov7Gn3n++1soPRfOXIRvXutvt\nVOPN57Y9S31SB9RcrLWzYR0mhvLAqBU2nqEw0/OBC9B5GxTPaTf/z7A28jnm7akz\nxvBp+qOzsK9LyD3KCoOWKW1ddhfFar3AU+1KYuG8SJzAUJ2RMAEoah6wW+4B1hVC\nUSUbbVTqxpK0xw7obW38XA1PgHHe0QEYjC9cAmTFKQRTFWduxtgEzCUbb8T8TUk5\nvuogTERm9pAxVIEJG75XvRv5OePAOVbDhVDWB2fG84ztDubiZ+zxFGpWnUhOTq33\nCZmyY3YbAgMBAAECggEAQMZcp0emLKGRY/mQZnw7wOsK0OJIWALlXIu9JbtgjS96\nJXLCQHIZ5ffdK+qmCqg1+fPItvYG/pQUu5chTiNxMISnelFLEs7EWTBql4Ik7DoY\n8HLMJ6LhvUHCAWzUCqr9yGWTlyFw0ztqK/Tqiz5zE2oYvxwOOGDNuTO0wVvzTSJj\nXN8M9Y2ZWF+UngLrhFYzEhGcTZT93/x0Ggajff/Pjfdz5mHR74vx3ZJpuF8nBKNi\n6boqHEk4N7JuLSmx3RLHoD4HCXnGuWpOEFZSi8m/HYtySKZFIVwANcO2l/OJJv3x\nBt/OrLW/pypL5HDjMOXaH2J06ackHhMxd+3LB6pufQKBgQDhDyzftieiY6BPK+yT\nxdkPKckvgaPK5DIuKGsnALNqbdudiOn1K/Q/6wOjImnR4bj7jm3GwBeY/yVICBjY\n4s3d038cbSEzGFvS+ZTJgoVCCYuQIFvrXsYjHDWMcmXc0LH6PR3vgU9BRklvo1jV\nNCndscp0puiOEeo6dFGcIXEOHQKBgQDV6biaFupq4626zRr4OAszm+wrkGTHEakk\nIyCFm4lXzzkbspsn/6sl8SJw3p4dKhJ/hOay+fk0UDSU3DHaL7hCWQUkUALHCtAw\nPiFUDQssfg/CpkP2mr69Zi7SL4UoJBj/tu1RwRYpM+H2EDXlBfTyFPP7dSFnE9n2\noAafNjc/lwKBgQCLmGgTEt8eoIDs2qfROOTbvOVnLBg2XripXLSp6otetmmEG0pS\nokLL6q/E3jGY11Nv5PY+UyPP6GJtfWg8DuH2d5rePOpc0P0TrW8WVnjlbxo7+XZK\nVey8FmE4jjSUdHYQaxxIVIKeUER4lG8jP0nAkuiq1mRkysPoIgIEv9FqGQKBgA08\nS97E4jZA5iPzwuJu3UqRMDi103Z5wkRpI/8AU6wqNzdegrkj2ZwcYmwnahMV4lUf\njQKv8tpoyAgZ47/DShxY07eed72HDsCdZ4SC1hknp6P8k6Hziy++3dDFffCw4xcX\nY3G2h79+5VFLSXplNvWvlDUP10RAdzEKT76UJTD7AoGACLL9/KG+zvh0uPzq4+0S\nr3oRSPEXvbeFcHarwIhG3C5KJVEABSlfi7bDAFml6ZS4kqYljlF7CkTEPuBKH2aB\nyaTMH0B/2vf2u2ul+HUdkua0FrLOWLoJVANce/t9eNDW7oKAbe3SUpkrsW2c4qHj\n8YI937GvlchTp6XvlwRYgwQ=\n-----END PRIVATE KEY-----\n"
            ]
        ]);

        \App\Domain\Identities\Models\ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'integration_id' => $this->integration->id,
            'provider' => 'google_workspace',
            'external_id' => 'ext-owner-123',
            'primary_email' => 'owner@test.com',
            'status' => 'linked'
        ]);

        \App\Domain\Identities\Models\ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'integration_id' => $this->integration->id,
            'provider' => 'google_workspace',
            'external_id' => 'ext-user-123',
            'primary_email' => 'user@test.com',
            'status' => 'linked'
        ]);

        \App\Domain\Identities\Models\ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->otherUser->id,
            'integration_id' => $this->integration->id,
            'provider' => 'google_workspace',
            'external_id' => 'ext-other-123',
            'primary_email' => 'other@test.com',
            'status' => 'linked'
        ]);
    }

    private function grantCalendarPermissionSelf(): void
    {
        $perm = Permission::where('slug', 'calendar.events.create')->first();
        $this->roleSelf->permissions()->sync([$perm->id => ['scope' => 'self']]);
    }

    private function actAsAI(User $user, ?Organization $org = null, array $headers = []): self
    {
        config(['services.ai_gateway.token' => 'test-token']);
        $defaultHeaders = [
            'Authorization'      => 'Bearer test-token',
            'X-User-UUID'        => $user->uuid,
            'X-Organization-UUID'=> ($org ?? $this->organization)->uuid,
        ];
        return $this->withHeaders(array_merge($defaultHeaders, $headers));
    }

    private function fakeGoogleCreateEvent(int $status = 200, array $response = []): void
    {
        if (empty($response)) {
            $response = [
                'id' => 'fake-event-id',
                'summary' => 'Meeting',
                'start' => ['dateTime' => '2026-08-11T14:00:00-03:00'],
                'end' => ['dateTime' => '2026-08-11T15:00:00-03:00'],
                'status' => 'confirmed'
            ];
        }

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-delegated-token', 'expires_in' => 3600], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy*' => Http::response(['calendars' => ['primary' => ['busy' => []]]], 200),
            '*' => Http::response($response, $status),
        ]);
    }

    public function test_missing_confirmation_returns_428()
    {
        $this->grantCalendarPermissionSelf();
        
        $response = $this->actAsAI($this->user)->postJson('/api/ai/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
        ]);

        $response->assertStatus(428)->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    }

    public function test_user_with_self_creates_event_with_confirmation()
    {
        $this->grantCalendarPermissionSelf();
        $this->fakeGoogleCreateEvent();

        $response = $this->actAsAI($this->user, null, [
            'X-Nodal-Action-Confirmed' => 'true',
            'X-Idempotency-Key' => 'idx-123'
        ])->postJson('/api/ai/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        
        $response->assertJsonPath('data.event.calendar.owner_user_uuid', $this->user->uuid);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai_calendar_event_create',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_with_self_cannot_create_for_other()
    {
        $this->grantCalendarPermissionSelf();
        $this->fakeGoogleCreateEvent();

        $response = $this->actAsAI($this->user, null, [
            'X-Nodal-Action-Confirmed' => 'true'
        ])->postJson('/api/ai/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
            'target_user_uuid' => $this->owner->uuid
        ]);

        $response->assertStatus(403);
    }

    public function test_user_without_external_identity_gets_403()
    {
        $this->grantCalendarPermissionSelf();
        $this->user->externalIdentities()->delete();

        $response = $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true'])
            ->postJson('/api/ai/calendar/events', [
                'title' => 'Meeting',
                'start' => '2026-08-11T14:00:00-03:00',
                'end' => '2026-08-11T15:00:00-03:00',
            ]);

        $response->assertStatus(403)->assertJsonPath('code', 'EXTERNAL_IDENTITY_REQUIRED');
    }

    public function test_user_without_capability_gets_403()
    {
        $response = $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true'])
            ->postJson('/api/ai/calendar/events', [
                'title' => 'Meeting',
                'start' => '2026-08-11T14:00:00-03:00',
                'end' => '2026-08-11T15:00:00-03:00',
            ]);

        $response->assertStatus(403);
    }

    public function test_validation_empty_title()
    {
        $response = $this->actAsAI($this->owner)->postJson('/api/ai/calendar/events', [
            'title' => '',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
        ]);
        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_validation_start_after_end()
    {
        $response = $this->actAsAI($this->owner)->postJson('/api/ai/calendar/events', [
            'title' => 'A',
            'start' => '2026-08-11T15:00:00-03:00',
            'end' => '2026-08-11T14:00:00-03:00',
        ]);
        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_internal_attendee_resolved()
    {
        $this->fakeGoogleCreateEvent();
        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])
            ->postJson('/api/ai/calendar/events', [
                'title' => 'Meeting',
                'start' => '2026-08-11T14:00:00-03:00',
                'end' => '2026-08-11T15:00:00-03:00',
                'attendees' => [
                    ['user_uuid' => $this->user->uuid]
                ]
            ]);

        $response->assertStatus(201);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->body(), 'user@test.com');
        });
    }

    public function test_create_meeting()
    {
        $this->fakeGoogleCreateEvent();
        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])
            ->postJson('/api/ai/calendar/events', [
                'title' => 'Meeting',
                'start' => '2026-08-11T14:00:00-03:00',
                'end' => '2026-08-11T15:00:00-03:00',
                'create_meeting' => true
            ]);

        $response->assertStatus(201);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'conferenceDataVersion=1') && str_contains($request->body(), 'conferenceSolutionKey');
        });
    }

    public function test_idempotency_prevents_duplicate_creation()
    {
        $this->fakeGoogleCreateEvent();
        
        $payload = [
            'title' => 'Meeting',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
        ];

        $response1 = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'idemp-123'])
            ->postJson('/api/ai/calendar/events', $payload);
        $response1->assertStatus(201);

        Http::fake([
            '*' => Http::response('Should not be called', 500)
        ]);

        $response2 = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'idemp-123'])
            ->postJson('/api/ai/calendar/events', $payload);
        
        $response2->assertStatus(200); 
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_conflict_handled_when_requested()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            ['start' => '2026-08-11T14:00:00-03:00', 'end' => '2026-08-11T15:00:00-03:00']
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-08-11T14:00:00-03:00',
            'end' => '2026-08-11T15:00:00-03:00',
            'check_conflicts' => true
        ]);

        $response->assertStatus(409)->assertJsonPath('code', 'EVENT_CONFLICT');
    }
}
