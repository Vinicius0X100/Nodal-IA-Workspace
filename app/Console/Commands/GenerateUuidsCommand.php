<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateUuidsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodal:generate-uuids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera UUIDs para todos os registros que ainda não possuem (útil após a migração para a nova arquitetura)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $models = [
            \App\Domain\Identity\Models\User::class,
            \App\Domain\Organizations\Models\Organization::class,
            \App\Domain\Roles\Models\Role::class,
            \App\Domain\Permissions\Models\Permission::class,
            \App\Domain\Integrations\Models\Integration::class,
            \App\Domain\Integrations\Models\IntegrationConfig::class,
            \App\Domain\Integrations\Models\IntegrationLog::class,
            \App\Domain\Organizations\Models\CompanyVerification::class,
            \App\Domain\Audit\Models\AuditLog::class,
            \App\Domain\Settings\Models\Setting::class,
        ];

        $this->info('Iniciando geração de UUIDs...');

        foreach ($models as $modelClass) {
            $this->info("Verificando {$modelClass}...");
            
            $records = $modelClass::whereNull('uuid')->get();
            $count = $records->count();
            
            if ($count === 0) {
                $this->line(" - Nenhum registro sem UUID encontrado.");
                continue;
            }

            $bar = $this->output->createProgressBar($count);
            
            foreach ($records as $record) {
                $record->uuid = (string) \Illuminate\Support\Str::uuid();
                // Salvamos usando query builder ou saveQuietly para não disparar eventos de 'updated_at' desnecessariamente
                $record->saveQuietly();
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info(" - {$count} UUIDs gerados com sucesso.");
        }

        $this->info('Processo concluído!');
    }
}
