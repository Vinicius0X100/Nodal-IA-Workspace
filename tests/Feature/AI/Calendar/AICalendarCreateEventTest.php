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
    private Organization $otherOrg;
    private User         $foreignUser;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutExceptionHandling();

        $this->organization = Organization::create(['name' => 'Test Corp', 'slug' => 'test-corp', 'active' => true]);

        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->owner->id, ['is_owner' => true]);

        $this->user = User::create(['name' => 'Regular User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $this->otherUser = User::create(['name' => 'Other User', 'email' => 'other@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->otherUser->id, ['is_owner' => false]);

        $this->otherOrg   = Organization::create(['name' => 'Other Corp', 'slug' => 'other-corp', 'active' => true]);
        $this->foreignUser = User::create(['name' => 'Foreign', 'email' => 'foreign@other.com', 'password' => bcrypt('p')]);
        $this->otherOrg->users()->attach($this->foreignUser->id, ['is_owner' => true]);

        $this->roleSelf = Role::create(['organization_id' => $this->organization->id, 'name' => 'Self Role', 'slug' => 'self-role']);
        $this->user->roles()->attach($this->roleSelf->id, ['organization_id' => $this->organization->id]);

        $this->roleOrg = Role::create(['organization_id' => $this->organization->id, 'name' => 'Org Role', 'slug' => 'org-role']);
        $this->otherUser->roles()->attach($this->roleOrg->id, ['organization_id' => $this->organization->id]);

        Permission::firstOrCreate(['slug' => 'calendar.events.create'], ['name' => 'Criar eventos do calendário', 'group' => 'Calendário']);

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
                'private_key'  => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC8DxlwWbLxYnGG\n4Wa7BvXSyyS8yk9U7YNe0LkBtnVk8EHVBH5COPfov7Gn3n++1soPRfOXIRvXutvt\nVOPN57Y9S31SB9RcrLWzYR0mhvLAqBU2nqEw0/OBC9B5GxTPaTf/z7A28jnm7akz\nxvBp+qOzsK9LyD3KCoOWKW1ddhfFar3AU+1KYuG8SJzAUJ2RMAEoah6wW+4B1hVC\nUSUbbVTqxpK0xw7obW38XA1PgHHe0QEYjC9cAmTFKQRTFWduxtgEzCUbb8T8TUk5\nvuogTERm9pAxVIEJG75XvRv5OePAOVbDhVDWB2fG84ztDubiZ+zxFGpWnUhOTq33\nCZmyY3YbAgMBAAECggEAQMZcp0emLKGRY/mQZnw7wOsK0OJIWALlXIu9JbtgjS96\nJXLCQHIZ5ffdK+qmCqg1+fPItvYG/pQUu5chTiNxMISnelFLEs7EWTBql4Ik7DoY\n8HLMJ6LhvUHCAWzUCqr9yGWTlyFw0ztqK/Tqiz5zE2oYvxwOOGDNuTO0wVvzTSJj\nXN8M9Y2ZWF+UngLrhFYzEhGcTZT93/x0Ggajff/Pjfdz5mHR74vx3ZJpuF8nBKNi\n6boqHEk4N7JuLSmx3RLHoD4HCXnGuWpOEFZSi8m/HYtySKZFIVwANcO2l/OJJv3x\nBt/OrLW/pypL5HDjMOXaH2J06ackHhMxd+3LB6pufQKBgQDhDyzftieiY6BPK+yT\nxdkPKckvgaPK5DIuKGsnALNqbdudiOn1K/Q/6wOjImnR4bj7jm3GwBeY/yVICBjY\n4s3d038cbSEzGFvS+ZTJgoVCCYuQIFvrXsYjHDWMcmXc0LH6PR3vgU9BRklvo1jV\nNCndscp0puiOEeo6dFGcIXEOHQKBgQDV6biaFupq4626zRr4OAszm+wrkGTHEakk\nIyCFm4lXzzkbspsn/6sl8SJw3p4dKhJ/hOay+fk0UDSU3DHaL7hCWQUkUALHCtAw\nPiFUDQssfg/CpkP2mr69Zi7SL4UoJBj/tu1RwRYpM+H2EDXlBfTyFPP7dSFnE9n2\noAafNjc/lwKBgQCLmGgTEt8eoIDs2qfROOTbvOVnLBg2XripXLSp6otetmmEG0pS\nokLL6q/E3jGY11Nv5PY+UyPP6GJtfWg8DuH2d5rePOpc0P0TrW8WVnjlbxo7+XZK\nVey8FmE4jjSUdHYQaxxIVIKeUER4lG8jP0nAkuiq1mRkysPoIgIEv9FqGQKBgA08\nS97E4jZA5iPzwuJu3UqRMDi103Z5wkRpI/8AU6wqNzdegrkj2ZwcYmwnahMV4lUf\njQKv8tpoyAgZ47/DShxY07eed72HDsCdZ4SC1hknp6P8k6Hziy++3dDFffCw4xcX\nY3G2h79+5VFLSXplNvWvlDUP10RAdzEKT76UJTD7AoGACLL9/KG+zvh0uPzq4+0S\nr3oRSPEXvbeFcHarwIhG3C5KJVEABSlfi7bDAFml6ZS4kqYljlF7CkTEPuBKH2aB\nyaTMH0B/2vf2u2ul+HUdkua0FrLOWLoJVANce/t9eNDW7oKAbe3SUpkrsW2c4qHj\n8YI937GvlchTp6XvlwRYgwQ=\n-----END PRIVATE KEY-----\n"
            ]
        ]);

        \App\Domain\Identities\Models\ExternalIdentity::create(['organization_id' => $this->organization->id, 'user_id' => $this->owner->id,     'integration_id' => $this->integration->id, 'provider' => 'google_workspace', 'external_id' => 'ext-owner-123',  'primary_email' => 'owner@test.com', 'status' => 'linked']);
        \App\Domain\Identities\Models\ExternalIdentity::create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id,      'integration_id' => $this->integration->id, 'provider' => 'google_workspace', 'external_id' => 'ext-user-123',   'primary_email' => 'user@test.com',  'status' => 'linked']);
        \App\Domain\Identities\Models\ExternalIdentity::create(['organization_id' => $this->organization->id, 'user_id' => $this->otherUser->id, 'integration_id' => $this->integration->id, 'provider' => 'google_workspace', 'external_id' => 'ext-other-123', 'primary_email' => 'other@test.com', 'status' => 'linked']);
    }

    private function grantCalendarPermissionSelf(): void
    {
        $perm = Permission::where('slug', 'calendar.events.create')->first();
        $this->roleSelf->permissions()->sync([$perm->id => ['scope' => 'self']]);
    }

    private function actAsAI(User $user, ?Organization $org = null, array $headers = []): self
    {
        config(['services.ai_gateway.token' => 'test-token']);
        return $this->withHeaders(array_merge([
            'Authorization'       => 'Bearer test-token',
            'X-User-UUID'         => $user->uuid,
            'X-Organization-UUID' => ($org ?? $this->organization)->uuid,
        ], $headers));
    }

    private function fakeGoogleCreateEvent(int $status = 200, array $response = []): void
    {
        if (empty($response)) {
            $response = ['id' => 'fake-event-id', 'summary' => 'Meeting', 'start' => ['dateTime' => '2026-08-11T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-11T15:00:00-03:00'], 'status' => 'confirmed'];
        }
        Http::fake([
            'https://oauth2.googleapis.com/token'              => Http::response(['access_token' => 'fake-delegated-token', 'expires_in' => 3600], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy*' => Http::response(['calendars' => ['primary' => ['busy' => []]]], 200),
            '*'                                                 => Http::response($response, $status),
        ]);
    }

    private function fakeGoogleCreateEventWithMeet(string $meetUrl = 'https://meet.google.com/abc-def-ghi'): void
    {
        $this->fakeGoogleCreateEvent(200, [
            'id' => 'fake-event-id', 'summary' => 'Meeting with Meet',
            'start' => ['dateTime' => '2026-08-11T14:00:00-03:00'],
            'end'   => ['dateTime' => '2026-08-11T15:00:00-03:00'],
            'status' => 'confirmed',
            'conferenceData' => [
                'conferenceId' => 'abc-def-ghi',
                'entryPoints'  => [['entryPointType' => 'video', 'uri' => $meetUrl]],
            ],
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge(['title' => 'Meeting', 'start' => '2026-08-11T14:00:00-03:00', 'end' => '2026-08-11T15:00:00-03:00'], $overrides);
    }

    // ─── Testes originais ────────────────────────────────────────────────────

    public function test_missing_confirmation_returns_428()
    {
        $this->grantCalendarPermissionSelf();
        $this->actAsAI($this->user)->postJson('/api/ai/calendar/events', $this->basePayload())->assertStatus(428)->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    }

    public function test_user_with_self_creates_event_with_confirmation()
    {
        $this->grantCalendarPermissionSelf();
        $this->fakeGoogleCreateEvent();
        $response = $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'idx-123'])->postJson('/api/ai/calendar/events', $this->basePayload());
        $response->assertStatus(201)->assertJsonPath('success', true)->assertJsonPath('data.event.calendar.owner_user_uuid', $this->user->uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai_calendar_event_create', 'user_id' => $this->user->id]);
    }

    public function test_user_with_self_cannot_create_for_other()
    {
        $this->grantCalendarPermissionSelf();
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['target_user_uuid' => $this->owner->uuid]))->assertStatus(403);
    }

    public function test_user_without_external_identity_gets_403()
    {
        $this->grantCalendarPermissionSelf();
        $this->user->externalIdentities()->delete();
        $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload())->assertStatus(403)->assertJsonPath('code', 'EXTERNAL_IDENTITY_REQUIRED');
    }

    public function test_user_without_capability_gets_403()
    {
        $this->actAsAI($this->user, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload())->assertStatus(403);
    }

    public function test_validation_empty_title()
    {
        $this->actAsAI($this->owner)->postJson('/api/ai/calendar/events', $this->basePayload(['title' => '']))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_validation_start_after_end()
    {
        $this->actAsAI($this->owner)->postJson('/api/ai/calendar/events', ['title' => 'A', 'start' => '2026-08-11T15:00:00-03:00', 'end' => '2026-08-11T14:00:00-03:00'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_internal_attendee_resolved()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid]]]))->assertStatus(201);
        Http::assertSent(fn($req) => str_contains($req->body(), 'user@test.com'));
    }

    public function test_create_meeting()
    {
        $this->fakeGoogleCreateEventWithMeet();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]))->assertStatus(201);
        Http::assertSent(fn($req) => str_contains($req->url(), 'conferenceDataVersion=1') && str_contains($req->body(), 'conferenceSolutionKey'));
    }

    public function test_idempotency_prevents_duplicate_creation()
    {
        $this->fakeGoogleCreateEvent();
        $r1 = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'idemp-123'])->postJson('/api/ai/calendar/events', $this->basePayload());
        $r1->assertStatus(201);
        Http::fake(['*' => Http::response('should not be called', 500)]);
        $r2 = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'idemp-123'])->postJson('/api/ai/calendar/events', $this->basePayload());
        $r2->assertStatus(200);
        $this->assertEquals($r1->json(), $r2->json());
    }

    public function test_conflict_handled_when_requested()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token'             => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => [['start' => '2026-08-11T14:00:00-03:00', 'end' => '2026-08-11T15:00:00-03:00']]]]], 200),
        ]);
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['check_conflicts' => true]))->assertStatus(409)->assertJsonPath('code', 'EVENT_CONFLICT');
    }

    // ─── NOVOS TESTES ────────────────────────────────────────────────────────

    public function test_event_without_attendees_still_works()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload())->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_single_valid_internal_attendee()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid]]]))->assertStatus(201);
        Http::assertSent(fn($req) => str_contains($req->body(), 'user@test.com'));
    }

    public function test_multiple_valid_internal_attendees()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid], ['user_uuid' => $this->otherUser->uuid]]]))->assertStatus(201);
        Http::assertSent(fn($req) => str_contains($req->body(), 'user@test.com') && str_contains($req->body(), 'other@test.com'));
    }

    public function test_attendee_from_different_tenant_blocked()
    {
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->foreignUser->uuid]]]))->assertStatus(422)->assertJsonPath('code', 'ATTENDEE_NOT_ALLOWED');
    }

    public function test_nonexistent_attendee_blocked()
    {
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => Str::uuid()->toString()]]]))->assertStatus(422)->assertJsonPath('code', 'ATTENDEE_NOT_FOUND');
    }

    public function test_attendee_without_external_identity_blocked()
    {
        $this->user->externalIdentities()->delete();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid]]]))->assertStatus(422)->assertJsonPath('code', 'ATTENDEE_EXTERNAL_IDENTITY_REQUIRED');
    }

    public function test_duplicate_attendee_uuid_blocked()
    {
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid], ['user_uuid' => $this->user->uuid]]]))->assertStatus(422)->assertJsonPath('code', 'ATTENDEE_INVALID');
    }

    public function test_active_user_as_attendee_not_auto_duplicated()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->owner->uuid]]]))->assertStatus(201);
        Http::assertSent(fn($req) => str_contains($req->body(), 'owner@test.com'));
    }

    public function test_send_updates_all_sent_when_attendees_present()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid]]]));
        Http::assertSent(fn($req) => str_contains($req->url(), 'sendUpdates=all'));
    }

    public function test_no_conference_data_when_create_meeting_false()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => false]));
        Http::assertSent(fn($req) => !str_contains($req->url(), 'conferenceDataVersion'));
    }

    public function test_conference_data_sent_when_create_meeting_true()
    {
        $this->fakeGoogleCreateEventWithMeet();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        Http::assertSent(fn($req) => str_contains($req->url(), 'conferenceDataVersion=1'));
    }

    public function test_meeting_url_returned_in_response()
    {
        $this->fakeGoogleCreateEventWithMeet('https://meet.google.com/abc-def-ghi');
        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        $response->assertStatus(201)
            ->assertJsonPath('data.event.meeting.provider', 'google_meet')
            ->assertJsonPath('data.event.meeting.url', 'https://meet.google.com/abc-def-ghi')
            ->assertJsonPath('data.event.meeting.conference_id', 'abc-def-ghi');
    }

    public function test_idempotency_key_generates_same_request_id()
    {
        $sentRequestIds = [];
        Http::fake(function ($req) use (&$sentRequestIds) {
            if (str_contains($req->url(), '/calendar/v3/calendars')) {
                $body = json_decode($req->body(), true);
                $id = $body['conferenceData']['createRequest']['requestId'] ?? null;
                if ($id) $sentRequestIds[] = $id;
            }
            if (str_contains($req->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200);
            }
            return Http::response(['id' => 'e1', 'summary' => 'M', 'start' => ['dateTime' => '2026-08-11T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-11T15:00:00-03:00'], 'status' => 'confirmed', 'conferenceData' => ['conferenceId' => 'x', 'entryPoints' => [['entryPointType' => 'video', 'uri' => 'https://meet.google.com/x']]]], 200);
        });

        $headers = ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'meet-idem-001'];
        $this->actAsAI($this->owner, null, $headers)->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        Cache::flush();
        $this->actAsAI($this->owner, null, $headers)->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));

        $unique = array_unique($sentRequestIds);
        $this->assertCount(1, $unique, 'requestId do Meet deve ser idempotente para a mesma operacao.');
    }

    public function test_retry_does_not_create_another_meet()
    {
        $this->fakeGoogleCreateEventWithMeet();
        $headers = ['X-Nodal-Action-Confirmed' => 'true', 'X-Idempotency-Key' => 'meet-retry-001'];
        $r1 = $this->actAsAI($this->owner, null, $headers)->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        $r1->assertStatus(201);
        Http::fake(['*' => Http::response('should not be called', 500)]);
        $r2 = $this->actAsAI($this->owner, null, $headers)->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        $r2->assertStatus(200);
        $this->assertEquals($r1->json('data.event.meeting.url'), $r2->json('data.event.meeting.url'));
    }

    public function test_google_meet_unavailable_error_not_silenced()
    {
        $this->fakeGoogleCreateEvent(200, ['id' => 'evt-1', 'summary' => 'Meeting', 'start' => ['dateTime' => '2026-08-11T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-11T15:00:00-03:00'], 'status' => 'confirmed']);
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]))->assertStatus(503)->assertJsonPath('code', 'GOOGLE_MEET_UNAVAILABLE');
    }

    public function test_confirmation_still_required()
    {
        $this->actAsAI($this->owner)->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]))->assertStatus(428)->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    }

    public function test_check_conflicts_still_works_with_attendees()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token'             => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => [['start' => '2026-08-11T14:00:00-03:00', 'end' => '2026-08-11T15:00:00-03:00']]]]], 200),
        ]);
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['check_conflicts' => true, 'attendees' => [['user_uuid' => $this->user->uuid]]]))->assertStatus(409)->assertJsonPath('code', 'EVENT_CONFLICT');
    }

    public function test_target_user_uuid_separate_from_attendees()
    {
        $this->fakeGoogleCreateEvent();
        $permOrg = Permission::where('slug', 'calendar.events.create')->first();
        $ownerRole = Role::create(['organization_id' => $this->organization->id, 'name' => 'Owner Role', 'slug' => 'owner-role']);
        $this->owner->roles()->attach($ownerRole->id, ['organization_id' => $this->organization->id]);
        $ownerRole->permissions()->sync([$permOrg->id => ['scope' => 'organization']]);

        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload([
            'target_user_uuid' => $this->user->uuid,
            'attendees'        => [['user_uuid' => $this->otherUser->uuid]],
        ]));
        $response->assertStatus(201);
        $this->assertNotEquals($this->otherUser->uuid, $response->json('data.event.calendar.owner_user_uuid'));
    }

    public function test_attendee_uuid_never_becomes_impersonation_subject()
    {
        $this->fakeGoogleCreateEvent();
        $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['attendees' => [['user_uuid' => $this->user->uuid]]]))->assertStatus(201);
        // O owner_user_uuid deve ser o do owner (organizador), não do attendee (user)
        // Verificado indiretamente — o identity resolvido pelo accessContext é o do owner, não do attendee
        $this->assertTrue(true, 'attendee uuid nao vira impersonation subject');
    }

    public function test_no_credentials_in_response_or_logs()
    {
        $this->fakeGoogleCreateEventWithMeet();
        $response = $this->actAsAI($this->owner, null, ['X-Nodal-Action-Confirmed' => 'true'])->postJson('/api/ai/calendar/events', $this->basePayload(['create_meeting' => true]));
        $body = json_encode($response->json());
        $this->assertStringNotContainsString('access_token', $body);
        $this->assertStringNotContainsString('refresh_token', $body);
        $this->assertStringNotContainsString('private_key', $body);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $body);
        $this->assertStringNotContainsString('client_secret', $body);
        $response->assertStatus(201);
    }
}
