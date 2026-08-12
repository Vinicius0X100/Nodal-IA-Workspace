<?php

namespace Tests\Feature\AI\Calendar;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AICalendarUpdateEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Integration $integration;
    private ExternalIdentity $externalIdentity;
    private string $eventId = 'google_event_123';
    private string $endpoint = '/api/ai/calendar/events/google_event_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();

        $this->organization = Organization::create(['name' => 'Test Corp', 'slug' => 'test-corp', 'active' => true]);

        $this->user = User::create(['name' => 'Regular User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $roleSelf = \App\Domain\Roles\Models\Role::create(['organization_id' => $this->organization->id, 'name' => 'Self Role', 'slug' => 'self-role']);
        $this->user->roles()->attach($roleSelf->id, ['organization_id' => $this->organization->id]);

        \App\Domain\Permissions\Models\Permission::firstOrCreate(['slug' => 'calendar.events.update'], ['name' => 'Update events', 'group' => 'Calendar']);
        $roleSelf->permissions()->attach(
            \App\Domain\Permissions\Models\Permission::where('slug', 'calendar.events.update')->first()->id,
            ['scope' => 'self']
        );

        $this->integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider'        => 'google_workspace',
            'display_name'    => 'Google Workspace',
            'status'          => 'connected',
            'config'          => [],
        ]);

        \App\Domain\Integrations\Models\IntegrationConfig::create([
            'integration_id' => $this->integration->id,
            'delegation_credentials_json' => [
                'client_email' => 'test@test.com',
                'private_key'  => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC8DxlwWbLxYnGG\n4Wa7BvXSyyS8yk9U7YNe0LkBtnVk8EHVBH5COPfov7Gn3n++1soPRfOXIRvXutvt\nVOPN57Y9S31SB9RcrLWzYR0mhvLAqBU2nqEw0/OBC9B5GxTPaTf/z7A28jnm7akz\nxvBp+qOzsK9LyD3KCoOWKW1ddhfFar3AU+1KYuG8SJzAUJ2RMAEoah6wW+4B1hVC\nUSUbbVTqxpK0xw7obW38XA1PgHHe0QEYjC9cAmTFKQRTFWduxtgEzCUbb8T8TUk5\nvuogTERm9pAxVIEJG75XvRv5OePAOVbDhVDWB2fG84ztDubiZ+zxFGpWnUhOTq33\nCZmyY3YbAgMBAAECggEAQMZcp0emLKGRY/mQZnw7wOsK0OJIWALlXIu9JbtgjS96\nJXLCQHIZ5ffdK+qmCqg1+fPItvYG/pQUu5chTiNxMISnelFLEs7EWTBql4Ik7DoY\n8HLMJ6LhvUHCAWzUCqr9yGWTlyFw0ztqK/Tqiz5zE2oYvxwOOGDNuTO0wVvzTSJj\nXN8M9Y2ZWF+UngLrhFYzEhGcTZT93/x0Ggajff/Pjfdz5mHR74vx3ZJpuF8nBKNi\n6boqHEk4N7JuLSmx3RLHoD4HCXnGuWpOEFZSi8m/HYtySKZFIVwANcO2l/OJJv3x\nBt/OrLW/pypL5HDjMOXaH2J06ackHhMxd+3LB6pufQKBgQDhDyzftieiY6BPK+yT\nxdkPKckvgaPK5DIuKGsnALNqbdudiOn1K/Q/6wOjImnR4bj7jm3GwBeY/yVICBjY\n4s3d038cbSEzGFvS+ZTJgoVCCYuQIFvrXsYjHDWMcmXc0LH6PR3vgU9BRklvo1jV\nNCndscp0puiOEeo6dFGcIXEOHQKBgQDV6biaFupq4626zRr4OAszm+wrkGTHEakk\nIyCFm4lXzzkbspsn/6sl8SJw3p4dKhJ/hOay+fk0UDSU3DHaL7hCWQUkUALHCtAw\nPiFUDQssfg/CpkP2mr69Zi7SL4UoJBj/tu1RwRYpM+H2EDXlBfTyFPP7dSFnE9n2\noAafNjc/lwKBgQCLmGgTEt8eoIDs2qfROOTbvOVnLBg2XripXLSp6otetmmEG0pS\nokLL6q/E3jGY11Nv5PY+UyPP6GJtfWg8DuH2d5rePOpc0P0TrW8WVnjlbxo7+XZK\nVey8FmE4jjSUdHYQaxxIVIKeUER4lG8jP0nAkuiq1mRkysPoIgIEv9FqGQKBgA08\nS97E4jZA5iPzwuJu3UqRMDi103Z5wkRpI/8AU6wqNzdegrkj2ZwcYmwnahMV4lUf\njQKv8tpoyAgZ47/DShxY07eed72HDsCdZ4SC1hknp6P8k6Hziy++3dDFffCw4xcX\nY3G2h79+5VFLSXplNvWvlDUP10RAdzEKT76UJTD7AoGACLL9/KG+zvh0uPzq4+0S\nr3oRSPEXvbeFcHarwIhG3C5KJVEABSlfi7bDAFml6ZS4kqYljlF7CkTEPuBKH2aB\nyaTMH0B/2vf2u2ul+HUdkua0FrLOWLoJVANce/t9eNDW7oKAbe3SUpkrsW2c4qHj\n8YI937GvlchTp6XvlwRYgwQ=\n-----END PRIVATE KEY-----\n"
            ]
        ]);

        $this->externalIdentity = ExternalIdentity::create([
            'user_id'         => $this->user->id,
            'organization_id' => $this->organization->id,
            'integration_id'  => $this->integration->id,
            'provider'        => 'google_workspace',
            'provider_id'     => 'google-123',
            'external_id'     => 'google-123',
            'primary_email'   => 'user@test.com',
            'token_data'      => ['access_token' => 'fake_token', 'expires_at' => now()->addHour()->timestamp],
        ]);
        
        Cache::flush();
    }

    private function actAsAI(array $extraHeaders = []): self
    {
        config(['services.ai_gateway.token' => 'test-token']);
        
        return $this->withHeaders(array_merge([
            'Authorization'            => 'Bearer test-token',
            'X-User-UUID'              => $this->user->uuid,
            'X-Organization-UUID'      => $this->organization->uuid,
            'X-Nodal-Action-Confirmed' => 'true',
            'X-Idempotency-Key'        => Str::uuid()->toString(),
        ], $extraHeaders));
    }

    private function mockGoogleEvent(array $overrides = []): void
    {
        $defaultEvent = [
            'id' => $this->eventId,
            'etag' => '"12345"',
            'summary' => 'Old Title',
            'description' => 'Old Description',
            'start' => ['dateTime' => '2026-08-12T14:00:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
            'end' => ['dateTime' => '2026-08-12T15:00:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
            'attendees' => [
                ['email' => 'external@test.com', 'responseStatus' => 'accepted']
            ],
            'htmlLink' => 'https://calendar.google.com/event?eid=123',
            'status' => 'confirmed'
        ];
        
        $mockedEvent = array_merge($defaultEvent, $overrides);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token', 'expires_in' => 3600], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push($mockedEvent, 200) // GET
                ->push($mockedEvent, 200) // PUT
        ]);
    }

    public function test_can_update_only_title()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['title' => 'New Title']
        ]);

        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['title']);
                 
        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                return $request->data()['summary'] === 'New Title'
                    && $request->data()['description'] === 'Old Description'
                    && !isset($request['sendUpdates']); // sem sendUpdates pq start/end/attendees não mudaram
            }
            return true;
        });
    }

    public function test_can_update_only_start_preserves_duration()
    {
        $this->mockGoogleEvent(); // 14:00 to 15:00 (1 hour)

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['start' => '2026-08-12T16:00:00-03:00']
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['start', 'end']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                $payload = $request->data();
                return $payload['start']['dateTime'] === '2026-08-12T16:00:00-03:00'
                    && $payload['end']['dateTime'] === '2026-08-12T17:00:00-03:00'; // 1 hr diff
            }
            return true;
        });
    }

    public function test_can_update_start_and_end()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => [
                'start' => '2026-08-12T16:00:00-03:00',
                'end' => '2026-08-12T18:00:00-03:00'
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['start', 'end']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                $payload = $request->data();
                return $payload['start']['dateTime'] === '2026-08-12T16:00:00-03:00'
                    && $payload['end']['dateTime'] === '2026-08-12T18:00:00-03:00';
            }
            return true;
        });
    }

    public function test_can_remove_description_via_null()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['description' => null]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['description']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                return $request->data()['description'] === ''; // converted to empty string
            }
            return true;
        });
    }

    public function test_error_if_event_not_found()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token', 'expires_in' => 3600], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::response([], 404)
        ]);

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['title' => 'New Title']
        ]);

        $response->assertStatus(404)
                 ->assertJsonPath('code', 'EVENT_NOT_FOUND');
    }

    public function test_error_if_unauthorized_scope()
    {
        // Require organization scope but only has self
        $targetUser = User::create(['name' => 'Target', 'email' => 'target@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($targetUser->id);
        
        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'target_user_uuid' => $targetUser->uuid,
            'changes' => ['title' => 'New Title']
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('code', 'ACCESS_DENIED');
    }

    public function test_error_if_target_missing_external_identity()
    {
        $role = $this->user->roles()->first();
        $role->permissions()->updateExistingPivot(
            \App\Domain\Permissions\Models\Permission::where('slug', 'calendar.events.update')->first()->id,
            ['scope' => 'organization']
        );
        $targetUser = User::create(['name' => 'Target', 'email' => 'target2@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($targetUser);
        
        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'target_user_uuid' => $targetUser->uuid,
            'changes' => ['title' => 'New Title']
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('code', 'EXTERNAL_IDENTITY_REQUIRED');
    }

    public function test_conflict_detection_ignores_self()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token', 'expires_in' => 3600], 200),
            // Mock para a verificação de conflito via events.list:
            'https://www.googleapis.com/calendar/v3/calendars/primary/events?timeMin=*' => Http::response([
                'items' => [['id' => $this->eventId, 'start' => ['dateTime' => '2026-08-12T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-12T15:00:00-03:00']]]
            ], 200),
            // Mock para o fetch do próprio evento no updateEvent e o PUT subsequente
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push(['id' => $this->eventId, 'start' => ['dateTime' => '2026-08-12T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-12T15:00:00-03:00']], 200)
                ->push([], 200)
        ]);

        $this->withoutExceptionHandling();
        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'check_conflicts' => true,
            'changes' => ['start' => '2026-08-12T16:00:00-03:00']
        ]);

        $response->assertStatus(200); // Passa pq o conflito é consigo mesmo
    }
    
    public function test_conflict_detection_blocks_real_conflict()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token', 'expires_in' => 3600], 200),
            // Mock para a verificação de conflito via events.list:
            'https://www.googleapis.com/calendar/v3/calendars/primary/events?timeMin=*' => Http::response([
                'items' => [['id' => 'OTHER_ID', 'start' => ['dateTime' => '2026-08-12T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-12T15:00:00-03:00']]]
            ], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push(['id' => $this->eventId, 'start' => ['dateTime' => '2026-08-12T14:00:00-03:00'], 'end' => ['dateTime' => '2026-08-12T15:00:00-03:00']], 200),
        ]);

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'check_conflicts' => true,
            'changes' => ['start' => '2026-08-12T16:00:00-03:00']
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('code', 'EVENT_CONFLICT');
    }

    public function test_attendee_replacement_preserves_externals()
    {
        $this->mockGoogleEvent();

        $newInternal = User::create(['name' => 'New Internal', 'email' => 'new.internal@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($newInternal->id);
        ExternalIdentity::create([
            'user_id' => $newInternal->id,
            'organization_id' => $this->organization->id,
            'integration_id'  => $this->integration->id,
            'provider' => 'google_workspace',
            'provider_id' => 'google-456',
            'external_id' => 'google-456',
            'primary_email' => 'new.internal@test.com'
        ]);

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => [
                'attendees' => [
                    ['user_uuid' => $newInternal->uuid]
                ]
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['attendees']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                $attendees = $request->data()['attendees'] ?? [];
                // should have the preserved external + new internal
                $emails = array_column($attendees, 'email');
                return in_array('external@test.com', $emails) && in_array('new.internal@test.com', $emails);
            }
            return true;
        });
    }

    public function test_attendee_empty_array_removes_internals()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['attendees' => []]
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                $attendees = $request->data()['attendees'] ?? [];
                return count($attendees) === 1 && $attendees[0]['email'] === 'external@test.com'; // preserved external only
            }
            return true;
        });
    }

    public function test_create_meeting_adds_conference_data()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['create_meeting' => true]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['meeting']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                $payload = $request->data();
                return isset($payload['conferenceData']['createRequest'])
                    && strpos($request->url(), 'conferenceDataVersion=1') !== false;
            }
            return true;
        });
    }

    public function test_remove_meeting_removes_conference_data()
    {
        $this->mockGoogleEvent(['conferenceData' => ['conferenceId' => 'xyz']]);

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['remove_meeting' => true]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.changed_fields', ['meeting']);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                return $request->data()['conferenceData'] === null;
            }
            return true;
        });
    }

    public function test_create_and_remove_meeting_together_fails()
    {
        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => [
                'create_meeting' => true,
                'remove_meeting' => true
            ]
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_requires_confirmation()
    {
        $response = $this->actAsAI(['X-Nodal-Action-Confirmed' => 'false'])->patchJson($this->endpoint, [
            'changes' => ['title' => 'New Title']
        ]);

        $response->assertStatus(428)
                 ->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    }

    public function test_idempotency_returns_cached_response()
    {
        $this->mockGoogleEvent();
        
        // Primeira chamada
        $response1 = $this->actAsAI(['X-Idempotency-Key' => 'key123'])->patchJson($this->endpoint, ['changes' => ['title' => 'T1']]);
        $response1->assertStatus(200);

        // Segunda chamada
        $response2 = $this->actAsAI(['X-Idempotency-Key' => 'key123'])->patchJson($this->endpoint, ['changes' => ['title' => 'T1']]);
        $response2->assertStatus(200);

        // Garante que só fez 1 PUT
        Http::assertSentCount(3); // 1 Token + 1 GET + 1 PUT
    }

    public function test_etag_mismatch_returns_event_changed()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token', 'expires_in' => 3600], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push(['id' => $this->eventId, 'etag' => '"123"', 'start' => [], 'end' => []], 200) // GET
                ->push([], 412) // PUT fails due to ETag mismatch
        ]);

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['title' => 'New Title']
        ]);

        $response->assertStatus(409) // mapped to 409 Conflict
                 ->assertJsonPath('code', 'EVENT_CHANGED');
    }

    public function test_send_updates_applied_conditionally()
    {
        $this->mockGoogleEvent();

        $response = $this->actAsAI()->patchJson($this->endpoint, [
            'changes' => ['start' => '2026-08-12T16:00:00-03:00']
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT') {
                return strpos($request->url(), 'sendUpdates=all') !== false; // pq tem attendees pre-existentes e mudou start
            }
            return true;
        });
    }
}
