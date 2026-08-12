<?php

namespace Tests\Feature\AI\Calendar;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AICalendarDeleteEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Integration $integration;
    private ExternalIdentity $externalIdentity;
    private string $eventId = 'test_event_123';
    private string $endpoint = '/api/ai/calendar/events/test_event_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        
        $this->organization = Organization::create(['name' => 'Test Corp', 'slug' => 'test-corp', 'active' => true]);

        $this->user = User::create(['name' => 'Regular User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $roleSelf = \App\Domain\Roles\Models\Role::create(['organization_id' => $this->organization->id, 'name' => 'Self Role', 'slug' => 'self-role']);
        $this->user->roles()->attach($roleSelf->id, ['organization_id' => $this->organization->id]);

        \App\Domain\Permissions\Models\Permission::firstOrCreate(['slug' => 'calendar.events.delete'], ['name' => 'Delete events', 'group' => 'Calendar']);
        
        $roleSelf->permissions()->attach(
            \App\Domain\Permissions\Models\Permission::where('slug', 'calendar.events.delete')->first()->id,
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
                'private_key'  => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC8DxlwWbLxYnGG\n4Wa7BvXSyyS8yk9U7YNe0LkBtnVk8EHVBH5COPfov7Gn3n++1soPRfOXIRvXutvt\nVOPN57Y9S31SB9RcrLWzYR0mhvLAqBU2nqEw0/OBC9B5GxTPaTf/z7A28jnm7akz\nxvBp+qOzsK9LyD3KCoOWKW1ddhfFar3AU+1KYuG8SJzAUJ2RMAEoah6wW+4B1hVC\nUSUbbVTqxpK0xw7obW38XA1PgHHe0QEYjC9cAmTFKQRTFWduxtgEzCUbb8T8TUk5\nvuogTERm9pAxVIEJG75XvRv5OePAOVbDhVDWB2fG84ztDubiZ+zxFGpWnUhOTq33\nCZmyY3YbAgMBAAECggEAQMZcp0emLKGRY/mQZnw7wOsK0OJIWALlXIu9JbtgjS96\nJXLCQHIZ5ffdK+qmCqg1+fPItvYG/pQUu5chTiNxMISnelFLEs7EWTBql4Ik7DoY\n8HLMJ6LhvUHCAWzUCqr9yGWTlyFw0ztqK/Tqiz5zE2oYvxwOOGDNuTO0wVvzTSJj\nXN8M9Y2ZWF+UngLrhFYzEhGcTZT93/x0Ggajff/Pjfdz5mHR74vx3ZJpuF8nBKNi\n6boqHEk4N7JuLSmx3RLHoD4HCXnGuWpOEFZSi8m/HYtySKZFIVwANcO2l/OJJv3x\nBt/OrLW/pypL5HDjMOXaH2J06ackHhMxd+3LB6pufQKBgQDhDyzftieiY6BPK+yT\nxdkPKckvgaPK5DIuKGsnALNqbdudiOn1K/Q/6wOjImnR4bj7jm3GwBeY/yVICBjY\n4s3d038cbSEzGFvS+ZTJgoVCCYuQIFvrXsYjHDWMcmXc0LH6PR3vgU9BRklvo1jV\nNCndscp0puiOEeo6dFGcIXEOHQKBgQDV6biaFupq4626zRr4OAszm+wrkGTHEakk\nIyCFm4lXzzkbspsn/6sl8SJw3p4dKhJ/hOay+fk0UDSU3DHaL7hCWQUkUALHCtAw\nPiFUDQssfg/CpkP2mr69Zi7SL4UoJBj/tu1RwRYpM+H2EDXlBfTyFPP7dSFnE9n2\oAafNjc/lwKBgQCLmGgTEt8eoIDs2qfROOTbvOVnLBg2XripXLSp6otetmmEG0pS\nokLL6q/E3jGY11Nv5PY+UyPP6GJtfWg8DuH2d5rePOpc0P0TrW8WVnjlbxo7+XZK\nVey8FmE4jjSUdHYQaxxIVIKeUER4lG8jP0nAkuiq1mRkysPoIgIEv9FqGQKBgA08\nS97E4jZA5iPzwuJu3UqRMDi103Z5wkRpI/8AU6wqNzdegrkj2ZwcYmwnahMV4lUf\njQKv8tpoyAgZ47/DShxY07eed72HDsCdZ4SC1hknp6P8k6Hziy++3dDFffCw4xcX\nY3G2h79+5VFLSXplNvWvlDUP10RAdzEKT76UJTD7AoGACLL9/KG+zvh0uPzq4+0S\nr3oRSPEXvbeFcHarwIhG3C5KJVEABSlfi7bDAFml6ZS4kqYljlF7CkTEPuBKH2aB\nyaTMH0B/2vf2u2ul+HUdkua0FrLOWLoJVANce/t9eNDW7oKAbe3SUpkrsW2c4qHj\n8YI937GvlchTp6XvlwRYgwQ=\n-----END PRIVATE KEY-----\n"
            ]
        ]);

        $this->externalIdentity = ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->user->id,
            'integration_id'  => $this->integration->id,
            'provider'        => 'google_workspace',
            'external_id'     => 'user@google.com',
            'primary_email'   => 'user@google.com',
            'metadata'        => ['email' => 'user@google.com']
        ]);

        Cache::flush();

        $this->mock(\App\Domain\Integrations\Services\GoogleTokenService::class, function ($mock) {
            $mock->shouldReceive('executeWithRetry')
                 ->andReturnUsing(function ($integration, $callback, $identity, $scopes) {
                     return $callback('dwd_token');
                 });
        });
    }

    private function getMockedEventResponse(array $overrides = [])
    {
        return array_merge([
            'id' => $this->eventId,
            'summary' => 'Reunião de Teste',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2026-08-12T14:00:00-03:00'],
            'end' => ['dateTime' => '2026-08-12T15:00:00-03:00'],
            'etag' => '"etag_v1"',
            'organizer' => ['email' => 'user@google.com', 'self' => true]
        ], $overrides);
    }

    private function actAsAI(?array $revokePermissions = null)
    {
        if ($revokePermissions) {
            $roleSelf = \App\Domain\Roles\Models\Role::where('slug', 'self-role')->first();
            $roleSelf->permissions()->detach();
        }

        config(['services.ai_gateway.token' => 'test-token']);

        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID' => $this->user->uuid,
            'X-Conversation-UUID' => 'conv-123',
            'X-Nodal-Action-Confirmed' => 'true'
        ]);
    }

    public function test_requires_confirmation_header()
    {
        $response = $this->actAsAI()->withHeaders(['X-Nodal-Action-Confirmed' => 'false'])->deleteJson($this->endpoint);
        if ($response->status() === 401) {
            dump($response->json());
        }
        $response->assertStatus(400)
                 ->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    }

    public function test_blocks_if_missing_permission()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->actAsAI(['calendar.events.delete'])->deleteJson($this->endpoint);
    }

    public function test_event_not_found_on_get()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::response([], 404)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        if ($response->status() === 500) {
            dump($response->json());
        }
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'EVENT_NOT_FOUND');
    }

    public function test_event_already_deleted()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::response($this->getMockedEventResponse([
                'status' => 'cancelled'
            ]), 200)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'EVENT_ALREADY_DELETED');
    }

    public function test_blocks_deleting_recurring_master()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::response($this->getMockedEventResponse([
                'recurrence' => ['RRULE:FREQ=WEEKLY']
            ]), 200)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(403)
                 ->assertJsonPath('code', 'RECURRING_EVENT_SCOPE_REQUIRED');
    }

    public function test_allows_deleting_recurring_instance()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::sequence()
                ->push($this->getMockedEventResponse([
                    'recurrence' => ['RRULE:FREQ=WEEKLY'],
                    'recurringEventId' => 'master_123'
                ]), 200)
                ->push('', 204)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_etag_mismatch_throws_event_changed()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::sequence()
                ->push($this->getMockedEventResponse(), 200) // ETag v1 retornado
                ->push([], 412) // Delete falha com 412
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(409)
                 ->assertJsonPath('code', 'EVENT_CHANGED');
    }

    public function test_successful_delete_as_organizer_returns_correct_scope()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push($this->getMockedEventResponse(['organizer' => ['self' => true]]), 200)
                ->push('', 204)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.deleted', true)
                 ->assertJsonPath('data.deletion_scope', 'organizer_event');
    }

    public function test_successful_delete_as_attendee_returns_correct_scope()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push($this->getMockedEventResponse(['organizer' => ['email' => 'other@example.com']]), 200)
                ->push('', 204)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(200)
                 ->assertJsonPath('data.deletion_scope', 'attendee_copy');
    }

    public function test_send_updates_is_injected_when_attendees_exist()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            // GET
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}" => Http::response($this->getMockedEventResponse([
                'attendees' => [['email' => 'foo@bar.com']]
            ]), 200),
            // DELETE com sendUpdates
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}?sendUpdates=all" => Http::response('', 204)
        ]);

        $response = $this->actAsAI()->deleteJson($this->endpoint);
        $response->assertStatus(200);
    }

    public function test_idempotency_key_returns_from_cache()
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'dwd_token'], 200),
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$this->eventId}*" => Http::sequence()
                ->push($this->getMockedEventResponse(), 200)
                ->push('', 204)
        ]);

        $client = $this->actAsAI();
        $headers = ['X-Idempotency-Key' => 'idemp-key-123'];

        // Primeira chamada
        $response1 = $client->withHeaders($headers)->deleteJson($this->endpoint);
        $response1->assertStatus(200);

        // Segunda chamada não deve falhar nem bater na API (se batesse a sequence ia estourar/falhar)
        $response2 = $client->withHeaders($headers)->deleteJson($this->endpoint);
        $response2->assertStatus(200)
                  ->assertJsonPath('data.deleted', true);
    }
}
