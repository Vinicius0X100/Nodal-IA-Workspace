<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactDraftChange;
use App\Domain\Artifacts\Models\SpreadsheetDraftChunk;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSpreadsheetDraftTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private string $token = 'test-ai-token';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->organization = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->user = User::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        
        // Mock AI Gateway authentication setup (assuming it just checks headers for this test, 
        // or we bypass middleware if it's strictly external. Wait, the middleware 'ai.gateway' requires a token usually.)
        // For testing, we might need to bypass it or set the right config.
        $this->withoutMiddleware(\App\Http\Middleware\AIGatewayMiddleware::class);
    }

    public function test_ai_creates_spreadsheet_draft_successfully()
    {
        $payload = [
            'title' => 'Orçamento Profissional',
            'sheets' => [
                [
                    'title' => 'Planilha1',
                    'updates' => [
                        [
                            'range' => 'A1:C2',
                            'values' => [
                                ['Produto', 'Qtd', 'Total'],
                                ['Teclado', 2, '=B2*150']
                            ]
                        ]
                    ],
                    'formatting' => [
                        [
                            'type' => 'format_range',
                            'range' => 'A1:C1',
                            'format' => ['bold' => true, 'background_color' => '#000']
                        ],
                        [
                            'type' => 'number_format',
                            'range' => 'C2:C2',
                            'format' => 'CURRENCY_BRL'
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/ai/artifacts/spreadsheets/drafts', $payload, [
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID' => $this->user->uuid,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'artifact_uuid', 'type', 'status', 'title', 'revision'
            ]
        ]);

        $uuid = $response->json('data.artifact_uuid');
        
        $draft = ArtifactDraft::where('uuid', $uuid)->first();
        $this->assertNotNull($draft);
        $this->assertEquals('spreadsheet', $draft->type);
        $this->assertEquals('Orçamento Profissional', $draft->title);
        $this->assertEquals(1, $draft->revision);
        
        // Check sheets
        $this->assertCount(1, $draft->sheets);
        $sheet = $draft->sheets->first();
        $this->assertEquals('Planilha1', $sheet->title);
        
        // Check chunks
        $chunks = SpreadsheetDraftChunk::where('sheet_id', $sheet->id)->get();
        $this->assertCount(1, $chunks);
        $this->assertEquals('Produto', $chunks->first()->payload_json['0']['0']['value']);
        $this->assertEquals('=B2*150', $chunks->first()->payload_json['1']['2']['formula']);
        
        // Check formatting rules
        $formats = $sheet->formats;
        $this->assertCount(2, $formats);
        $this->assertEquals(1, $formats[0]->revision); // revision 1
        $this->assertEquals(0, $formats[0]->operation_index); // index 0
        $this->assertEquals('#000', $formats[0]->format_json['background_color']);
        
        // Check Journal
        $changes = ArtifactDraftChange::where('artifact_draft_id', $draft->id)->get();
        $this->assertCount(1, $changes);
        $this->assertEquals('draft_created', $changes->first()->type);
        $this->assertEquals(1, $changes->first()->revision);
    }
    
    public function test_ai_create_fails_without_organization()
    {
        $response = $this->postJson('/api/ai/artifacts/spreadsheets/drafts', ['title' => 'Test']);
        $response->assertStatus(404);
    }
}
