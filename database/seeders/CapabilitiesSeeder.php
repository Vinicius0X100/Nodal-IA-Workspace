<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CapabilitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Domain\Permissions\Models\Permission::updateOrCreate(
            ['slug' => 'gmail.messages.read'],
            [
                'name' => 'Pesquisar e Ler E-mails',
                'description' => 'Permissão de leitura/pesquisa de e-mails no Gmail.',
                'group' => 'Gmail',
                'is_system' => true,
            ]
        );

        \App\Domain\Permissions\Models\Permission::updateOrCreate(
            ['slug' => 'gmail.attachments.download'],
            [
                'name' => 'Download de Anexos',
                'description' => 'Permissão para baixar anexos do Gmail.',
                'group' => 'Gmail',
                'is_system' => true,
            ]
        );
    }
}
