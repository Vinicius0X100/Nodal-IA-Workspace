<?php

namespace App\Console\Commands;

use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCommitIdentity;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCreateCommand;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValueRange;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValuesBatch;
use App\Domain\Artifacts\Providers\Google\GoogleSpreadsheetProvider;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestGoogleSpreadsheetProviderCommand extends Command
{
    protected $signature = 'test:google-spreadsheet-provider {integration_id}';
    protected $description = 'Run a smoke test for the Google Spreadsheet Provider';

    public function handle(GoogleSpreadsheetProvider $provider)
    {
        $integrationId = $this->argument('integration_id');
        $integration = Integration::findOrFail($integrationId);

        $this->info("Starting Smoke Test with Integration ID: {$integrationId}");
        
        // This is a dev-only command so we assume a generic identity context
        // You might need to adjust this depending on how identities are fetched in dev
        $identityContext = null; // Can pass ExternalIdentity if really needed by your setup

        $provider->setContext($integration, $identityContext);

        $commitUuid = (string) Str::uuid();
        $createCommand = new SpreadsheetCreateCommand('Smoke Test Spreadsheet - ' . now()->format('Y-m-d H:i:s'), new SpreadsheetCommitIdentity($commitUuid));
        
        $this->info("1. Creating Spreadsheet...");
        $resource = $provider->createSpreadsheet($createCommand);
        $this->info("Created! URL: " . $resource->externalUrl);

        $this->info("2. Finding by Commit Key...");
        $found = $provider->findByCommitKey(new SpreadsheetCommitIdentity($commitUuid));
        if ($found && $found->externalId === $resource->externalId) {
            $this->info("Found successfully.");
        } else {
            $this->error("Failed to find by commit key.");
        }

        $this->info("3. Preparing Structure...");
        $sheetUuid1 = (string) Str::uuid();
        $sheetUuid2 = (string) Str::uuid();
        $structureBatch = new SpreadsheetStructureBatch(
            sheetsToCreate: [
                ['uuid' => $sheetUuid1, 'title' => 'First Sheet', 'index' => 0],
                ['uuid' => $sheetUuid2, 'title' => 'Second Sheet', 'index' => 1]
            ],
            sheetsToRename: [],
            firstSheetUuid: $sheetUuid1
        );
        $structureResult = $provider->prepareStructure($resource, $structureBatch);
        $this->info("Structure Prepared. Returned handles: " . count($structureResult->sheetHandles));

        $this->info("4. Writing Values...");
        $handle1 = $structureResult->sheetHandles[0];
        $valuesBatch = new SpreadsheetValuesBatch($handle1, [
            new SpreadsheetValueRange(0, 0, 1, 1, [
                ['A1', 'B1'],
                ['=A1&B1', null]
            ])
        ]);
        $provider->writeValues($resource, $valuesBatch);
        $this->info("Values written.");

        $this->info("5. Applying Formatting...");
        $formatBatch = new SpreadsheetFormatBatch($handle1, [
            new SpreadsheetFormatOperation('background_color', 0, 0, 0, 0, ['val' => '#FF0000']),
            new SpreadsheetFormatOperation('text_style', 0, 0, 1, 1, ['key' => 'bold', 'val' => true]),
            new SpreadsheetFormatOperation('freeze', 0, 0, 0, 0, ['val' => null, 'rows' => 1, 'columns' => 0]),
        ]);
        $provider->applyFormatting($resource, $formatBatch);
        $this->info("Formatting applied.");

        $this->info("Smoke Test Completed Successfully.");
        $this->info("URL: " . $resource->externalUrl);
        $this->info("Remember to manually check the URL, then you can run a cleanup if necessary.");
    }
}
