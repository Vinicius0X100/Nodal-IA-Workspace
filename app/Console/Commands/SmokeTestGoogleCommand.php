<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SmokeTestGoogleCommand extends Command
{
    protected $signature = 'nodal:smoke-test-google';
    protected $description = 'Executa o Smoke Test do Google Sheets (Commit Integrado)';

    public function handle(\App\Domain\Artifacts\Services\ArtifactCommitService $commitService)
    {
        $this->info("Iniciando Smoke Test do Google Sheets...");
        
        $org = \App\Domain\Organizations\Models\Organization::first();
        if (!$org) {
            $this->error("Nenhuma organização encontrada. Crie uma para rodar o teste.");
            return;
        }
        
        $integration = \App\Domain\Integrations\Models\Integration::where('provider', 'google_workspace')
            ->where('organization_id', $org->id)
            ->first();
            
        if (!$integration || !$integration->is_enabled) {
            $this->error("Integração Google Workspace não encontrada ou desabilitada para a Org ID: {$org->id}");
            $this->info("Configure o banco de dados local com um token válido de Google OAuth2 para prosseguir.");
            return;
        }

        $draft = \App\Domain\Artifacts\Models\ArtifactDraft::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Smoke Test - Nodal',
            'status' => 'draft',
            'revision' => 1
        ]);
        
        // Sheet 1
        $sheet1 = \App\Domain\Artifacts\Models\SpreadsheetDraftSheet::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'index' => 0,
            'title' => 'Dashboard',
        ]);
        
        // Sheet 2
        $sheet2 = \App\Domain\Artifacts\Models\SpreadsheetDraftSheet::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'index' => 1,
            'title' => 'Data',
        ]);
        
        // Populate Sheet 1 (Values & Formats)
        // Values: title and numbers
        $sheet1->chunks()->create([
            'artifact_draft_id' => $draft->id,
            'revision' => 1,
            'start_row' => 0, 'end_row' => 1,
            'start_col' => 0, 'end_col' => 3,
            'payload' => [
                ['Merged Title', null, null, null],
                ['Revenue', 'Expenses', 'Profit', 'Margin']
            ]
        ]);
        
        // Formats: bold, freeze, merge A1:D1
        $sheet1->formatChunks()->create([
            'artifact_draft_id' => $draft->id,
            'revision' => 1,
            'type' => 'text_style',
            'start_row' => 1, 'end_row' => 1,
            'start_col' => 0, 'end_col' => 3,
            'payload' => ['key' => 'bold', 'val' => true]
        ]);
        $sheet1->formatChunks()->create([
            'artifact_draft_id' => $draft->id,
            'revision' => 1,
            'type' => 'background_color',
            'start_row' => 1, 'end_row' => 1,
            'start_col' => 0, 'end_col' => 3,
            'payload' => ['key' => 'background_color', 'val' => '#DDDDDD']
        ]);
        $sheet1->formatChunks()->create([
            'artifact_draft_id' => $draft->id,
            'revision' => 1,
            'type' => 'freeze',
            'start_row' => 0, 'end_row' => 0,
            'start_col' => 0, 'end_col' => 0,
            'payload' => ['rows' => 2, 'columns' => 1]
        ]);
        $sheet1->merges()->create([
            'artifact_draft_id' => $draft->id,
            'start_row' => 0, 'end_row' => 0,
            'start_col' => 0, 'end_col' => 3
        ]);
        
        // Commit
        $this->info("Draft {$draft->uuid} criado com 2 sheets, valores, formatos e merges.");
        $this->info("Executando Commit 1...");
        
        $result = $commitService->commit($draft->uuid, $org->id);
        
        $this->info("POST /commit retornou status: {$result['status']} | Commit UUID: {$result['commit_uuid']}");
        $this->info("Iniciando polling do worker...");
        
        while (true) {
            sleep(2);
            $status = $commitService->getStatus($draft->uuid, $org->id);
            $this->info("Status atual: {$status['status']} - Estágio: {$status['stage']}");
            
            if ($status['status'] === 'committed' || $status['status'] === 'failed') {
                break;
            }
        }
        
        if ($status['status'] === 'failed') {
            $this->error("Commit 1 falhou.");
            dump($status['error'] ?? []);
            return;
        }
        
        $this->info("Commit 1 SUCESSO! Resource UUID: {$status['resource_uuid']}");
        $this->info("Abra o Google Drive para verificar o arquivo gerado.");
        
        if ($this->confirm('Deseja disparar o SEGUNDO COMMIT idêntico para testar a idempotência? (Certifique-se que o worker rodou)')) {
            $this->info("Modificando status do draft para testar idempotencia local...");
            $draft->update(['status' => 'draft']); // Simulate an edit occurred
            
            $result2 = $commitService->commit($draft->uuid, $org->id);
            $this->info("POST /commit 2 retornou. Commit UUID: {$result2['commit_uuid']}");
            
            while (true) {
                sleep(2);
                $status2 = $commitService->getStatus($draft->uuid, $org->id);
                $this->info("Status atual 2: {$status2['status']} - Estágio: {$status2['stage']}");
                
                if ($status2['status'] === 'committed' || $status2['status'] === 'failed') {
                    break;
                }
            }
            
            if ($status2['status'] === 'committed') {
                $this->info("Commit 2 SUCESSO. Resource UUID mantido: {$status2['resource_uuid']}");
                $this->info("Testes Completos! Verifique a ausência de abas duplicadas e formatações duplicadas no Google Sheets.");
            }
        }
    }
}
