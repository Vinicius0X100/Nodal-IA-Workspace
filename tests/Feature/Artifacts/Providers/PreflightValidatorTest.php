<?php

namespace Tests\Feature\Artifacts\Providers;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnsupportedOperationException;
use App\Domain\Artifacts\Providers\Materialization\SpreadsheetPreflightValidator;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreflightValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_passes_supported_operations()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        $user = User::create(['organization_id' => $org->id, 'name' => 'User', 'email' => 'u1@ex.com', 'password' => '123']);

        $draft = ArtifactDraft::create([
            'uuid' => Str::uuid(),
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'draft',
            'schema_version' => 1,
            'revision' => 1,
        ]);
        $draft->sheets()->create(['uuid' => Str::uuid(), 'index' => 0, 'title' => 'Sheet 1']);
        
        $caps = new SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 500, 10, 10, 10, 1024);
        
        $validator = new SpreadsheetPreflightValidator();
        $this->assertTrue($validator->validate($draft, $caps));
    }
    
    public function test_it_fails_multiple_sheets()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-2']);
        $user = User::create(['organization_id' => $org->id, 'name' => 'User', 'email' => 'u2@ex.com', 'password' => '123']);

        $draft = ArtifactDraft::create([
            'uuid' => Str::uuid(),
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'draft',
            'schema_version' => 1,
            'revision' => 1,
        ]);
        $draft->sheets()->create(['uuid' => Str::uuid(), 'index' => 0, 'title' => 'Sheet 1']);
        $draft->sheets()->create(['uuid' => Str::uuid(), 'index' => 1, 'title' => 'Sheet 2']);
        
        $caps = new SpreadsheetProviderCapabilities(false, true, true, true, true, true, true, 500, 10, 10, 10, 1024);
        
        $this->expectException(SpreadsheetProviderUnsupportedOperationException::class);
        $this->expectExceptionMessage("Provider does not support multiple sheets.");
        
        $validator = new SpreadsheetPreflightValidator();
        $validator->validate($draft, $caps);
    }
}
