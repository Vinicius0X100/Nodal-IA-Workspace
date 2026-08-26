<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Domain\Permissions\Models\Permission::updateOrCreate(
            ['slug' => 'meta.read'],
            [
                'name' => 'Visualizar Meta Ads',
                'group' => 'Meta',
                'description' => 'Permite consultar contas, campanhas, insights e recursos da Meta via IA.',
            ]
        );
    }

    public function down(): void
    {
        \App\Domain\Permissions\Models\Permission::where('slug', 'meta.read')->delete();
    }
};
