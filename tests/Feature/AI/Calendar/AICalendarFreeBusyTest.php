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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AICalendarFreeBusyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;
    private User $regularUser;
    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Cria a org
        $this->organization = Organization::create([
            'name' => 'Tech Corp',
            'slug' => 'tech-corp',
            'active' => true,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'timezone' => 'America/Sao_Paulo',
        ]);

        // 2. Cria os usuários
        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@techcorp.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->owner->id, ['is_owner' => true]);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@techcorp.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->regularUser->id, ['is_owner' => false]);

        // 3. Mock Integration e Config
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
            'primary_email' => 'owner@techcorp.com',
            'status' => 'linked'
        ]);

        \App\Domain\Identities\Models\ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->regularUser->id,
            'integration_id' => $this->integration->id,
            'provider' => 'google_workspace',
            'external_id' => 'ext-user-123',
            'primary_email' => 'user@techcorp.com',
            'status' => 'linked'
        ]);
    }

    private function grantFreeBusyPermission(): void
    {
        $role = Role::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Calendar Viewer',
            'slug'            => 'calendar-viewer',
            'description'     => 'Viewer',
        ]);

        $permission = Permission::firstOrCreate(['slug' => 'calendar.freebusy.read'], ['name' => 'Read FreeBusy']);
        $role->permissions()->attach($permission->id);
        $this->regularUser->roles()->attach($role->id, ['organization_id' => $this->organization->id]);
    }

    private function actAsAI(User $user, ?Organization $org = null): \Illuminate\Testing\TestResponse|self
    {
        config(['services.ai_gateway.token' => 'test-token']);
        $org = $org ?? $this->organization;
        return $this->withHeaders([
            'Authorization'       => 'Bearer test-token',
            'X-Organization-UUID' => $org->uuid,
            'X-User-UUID'         => $user->uuid,
        ]);
    }

    private function fakeGoogleFreeBusy(array $busyIntervals = [], int $status = 200, array $extraResponseData = []): void
    {
        $response = array_merge([
            'kind' => 'calendar#freeBusy',
            'timeMin' => '2026-08-11T09:00:00-03:00',
            'timeMax' => '2026-08-11T18:00:00-03:00',
            'calendars' => [
                'primary' => [
                    'busy' => $busyIntervals
                ]
            ]
        ], $extraResponseData);

        Http::fake([
            'googleapis.com/calendar/v3/freeBusy*' => Http::response($response, $status),
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in'   => 3600,
            ], 200),
        ]);
    }

    private function postFreebusy(User $user, array $payload = [])
    {
        $defaultPayload = [
            'start' => '2026-08-11T09:00:00-03:00',
            'end'   => '2026-08-11T18:00:00-03:00',
        ];
        return $this->actAsAI($user)->postJson('/api/ai/calendar/freebusy', array_merge($defaultPayload, $payload));
    }

    // ── 1. Período totalmente livre ──────────────────────────────────────────
    public function test_fully_free_period()
    {
        $this->fakeGoogleFreeBusy([]);

        $response = $this->postFreebusy($this->owner);
        if ($response->status() !== 200) {
            $response->dump();
        }

        $response->assertOk()
            ->assertJsonPath('data.is_fully_free', true)
            ->assertJsonPath('data.is_fully_busy', false)
            ->assertJsonCount(0, 'data.busy')
            ->assertJsonCount(1, 'data.free')
            ->assertJsonPath('data.free.0.duration_minutes', 540); // 9 hours
    }

    // ── 2. Período totalmente ocupado ────────────────────────────────────────
    public function test_fully_busy_period()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T09:00:00-03:00', 'end' => '2026-08-11T18:00:00-03:00']
        ]);

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonPath('data.is_fully_free', false)
            ->assertJsonPath('data.is_fully_busy', true)
            ->assertJsonCount(1, 'data.busy')
            ->assertJsonCount(0, 'data.free');
    }

    // ── 3. Um intervalo ocupado no meio ──────────────────────────────────────
    public function test_one_busy_interval_in_the_middle()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T12:00:00-03:00', 'end' => '2026-08-11T13:00:00-03:00']
        ]);

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonPath('data.is_fully_free', false)
            ->assertJsonPath('data.is_fully_busy', false)
            ->assertJsonCount(2, 'data.free')
            ->assertJsonPath('data.free.0.start', '2026-08-11T09:00:00-03:00')
            ->assertJsonPath('data.free.0.end', '2026-08-11T12:00:00-03:00')
            ->assertJsonPath('data.free.1.start', '2026-08-11T13:00:00-03:00')
            ->assertJsonPath('data.free.1.end', '2026-08-11T18:00:00-03:00');
    }

    // ── 4. Vários intervalos ocupados ────────────────────────────────────────
    public function test_multiple_busy_intervals()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T10:00:00-03:00', 'end' => '2026-08-11T11:00:00-03:00'],
            ['start' => '2026-08-11T15:00:00-03:00', 'end' => '2026-08-11T16:00:00-03:00']
        ]);

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonCount(2, 'data.busy')
            ->assertJsonCount(3, 'data.free');
    }

    // ── 5. Intervalos sobrepostos (merge logic) ──────────────────────────────
    public function test_overlapping_intervals_are_merged()
    {
        // Google não costuma retornar sobrepostos desordenados em freeBusy, mas a spec pede para garantir o merge.
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T10:00:00-03:00', 'end' => '2026-08-11T12:00:00-03:00'],
            ['start' => '2026-08-11T11:00:00-03:00', 'end' => '2026-08-11T13:00:00-03:00'],
        ]);

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonCount(1, 'data.busy') // Foram fundidos em 1 bloco: 10:00 às 13:00
            ->assertJsonPath('data.busy.0.end', '2026-08-11T13:00:00-03:00')
            ->assertJsonCount(2, 'data.free'); // 9-10 e 13-18
    }

    // ── 6. Intervalos consecutivos ───────────────────────────────────────────
    public function test_consecutive_intervals_are_merged()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T10:00:00-03:00', 'end' => '2026-08-11T11:00:00-03:00'],
            ['start' => '2026-08-11T11:00:00-03:00', 'end' => '2026-08-11T12:00:00-03:00'],
        ]);

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonCount(1, 'data.busy') // Fundidos: 10:00 às 12:00
            ->assertJsonPath('data.busy.0.end', '2026-08-11T12:00:00-03:00');
    }

    // ── 7 & 8. Cálculo de free slots e slot mínimo (30 vs 60 mins) ───────────
    public function test_minimum_slot_duration_discards_short_free_windows()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T09:00:00-03:00', 'end' => '2026-08-11T13:00:00-03:00'], // Free 1: N/A
            // gap 13:00 to 13:30 (30 mins free)
            ['start' => '2026-08-11T13:30:00-03:00', 'end' => '2026-08-11T17:00:00-03:00'],
            // gap 17:00 to 18:00 (60 mins free)
        ]);

        // Com o padrão (30 mins), ambas janelas aparecem
        $response30 = $this->postFreebusy($this->owner);
        $response30->assertOk()->assertJsonCount(2, 'data.free');

        // Passando slot de 60 minutos, a janela de 30 deve ser descartada
        $response60 = $this->postFreebusy($this->owner, ['slot_duration_minutes' => 60]);
        $response60->assertOk()
            ->assertJsonCount(1, 'data.free') // Apenas a janela de 17:00-18:00
            ->assertJsonPath('data.free.0.duration_minutes', 60);
    }

    // ── 9. Start >= end retorna erro ─────────────────────────────────────────
    public function test_start_greater_than_end_is_rejected()
    {
        $response = $this->postFreebusy($this->owner, [
            'start' => '2026-08-11T18:00:00-03:00',
            'end'   => '2026-08-11T09:00:00-03:00', // start > end
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
    }

    // ── 10. Usuário sem capability recebe 403 ────────────────────────────────
    public function test_user_without_capability_receives_403()
    {
        $response = $this->postFreebusy($this->regularUser);
        $response->assertStatus(403);
    }

    // ── 11. Owner faz bypass e Regular User com permissão funciona ───────────
    public function test_authorization_bypass_and_permission()
    {
        $this->fakeGoogleFreeBusy();

        // Owner já passou no teste 1.
        $this->grantFreeBusyPermission();
        $response = $this->postFreebusy($this->regularUser);
        $response->assertOk();
    }

    // ── 12. Tenant diferente não acessa ──────────────────────────────────────
    public function test_cross_tenant_access_denied()
    {
        $otherOrg = Organization::create(['name' => 'Other', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        
        $response = $this->actAsAI($this->owner, $otherOrg)
            ->postJson('/api/ai/calendar/freebusy', [
                'start' => '2026-08-11T09:00:00-03:00',
                'end'   => '2026-08-11T18:00:00-03:00',
            ]);
            
        $response->assertStatus(403); // Owner de org A não tem bypass na org B
    }

    // ── 13. Integração desconectada ──────────────────────────────────────────
    public function test_fails_if_integration_disconnected()
    {
        $this->integration->update(['status' => 'disconnected']);
        $response = $this->postFreebusy($this->owner);
        $response->assertStatus(503)->assertJsonPath('code', 'GOOGLE_CALENDAR_UNAVAILABLE');
    }

    // ── 14 & 15. Token expirado faz refresh e retry no 401 ───────────────────
    public function test_expired_token_is_refreshed()
    {
        \Illuminate\Support\Facades\DB::table('integrations')
            ->where('id', $this->integration->id)
            ->update(['token_expires_at' => now()->subMinutes(10)->toDateTimeString()]);

        $this->fakeGoogleFreeBusy();

        $response = $this->postFreebusy($this->owner);
        $response->assertOk();

        // Verifica que /token foi chamado
        Http::assertSent(fn($req) => str_contains($req->url(), 'oauth2.googleapis.com/token'));
    }

    public function test_single_retry_on_401()
    {
        Http::fake([
            'googleapis.com/calendar/v3/freeBusy*' => Http::sequence()
                ->push(['error' => 'unauthorized'], 401)
                ->push(['timeMin' => '...', 'calendars' => ['primary' => ['busy' => []]]], 200),
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-tok', 'expires_in' => 3600], 200),
        ]);

        $response = $this->postFreebusy($this->owner);
        $response->assertOk();
    }

    // ── 16. invalid_grant faz reauth ─────────────────────────────────────────
    public function test_invalid_grant_prompts_reauth()
    {
        \Illuminate\Support\Facades\DB::table('integrations')
            ->where('id', $this->integration->id)
            ->update(['token_expires_at' => now()->subMinutes(10)->toDateTimeString()]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->postFreebusy($this->owner);
        
        $response->assertStatus(503)->assertJsonPath('code', 'GOOGLE_CALENDAR_UNAVAILABLE');
        
        $this->assertEquals('needs_reconnect', $this->integration->fresh()->status);
    }

    // ── 17. Nenhum detalhe de evento vaza ────────────────────────────────────
    public function test_no_event_details_are_exposed()
    {
        $this->fakeGoogleFreeBusy([
            ['start' => '2026-08-11T10:00:00-03:00', 'end' => '2026-08-11T11:00:00-03:00']
        ]);

        $response = $this->postFreebusy($this->owner);
        $json = $response->json();

        // Garante que chaves como title, summary, location NÃO existem
        $this->assertArrayNotHasKey('title', $json['data']['busy'][0] ?? []);
        $this->assertArrayNotHasKey('summary', $json['data']['busy'][0] ?? []);
        $this->assertArrayNotHasKey('location', $json['data']['busy'][0] ?? []);
        $this->assertArrayHasKey('start', $json['data']['busy'][0]);
    }

    // ── 18 & 19. Timezone e calendar primário padrão ─────────────────────────
    public function test_timezone_and_default_calendar()
    {
        $this->fakeGoogleFreeBusy();

        $response = $this->postFreebusy($this->owner);

        $response->assertOk()
            ->assertJsonPath('data.calendar.id', 'primary')
            ->assertJsonPath('data.calendar.time_zone', 'America/Sao_Paulo'); // Herdado da organization

        Http::assertSent(function ($req) {
            if (str_contains($req->url(), 'freeBusy')) {
                $body = json_decode($req->body(), true);
                return $body['timeZone'] === 'America/Sao_Paulo' &&
                       $body['items'][0]['id'] === 'primary';
            }
            return false;
        });
    }

    // ── 20. Resposta sem tokens ──────────────────────────────────────────────
    public function test_response_contains_no_tokens()
    {
        $this->fakeGoogleFreeBusy();

        $response = $this->postFreebusy($this->owner);
        $json = json_encode($response->json());

        $this->assertStringNotContainsString('fake-access-token', $json);
        $this->assertStringNotContainsString('new-access-token', $json);
        $this->assertStringNotContainsString('fake-refresh-token', $json);
    }
}
