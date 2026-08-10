<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \App\Domain\Permissions\Models\Permission::updateOrCreate(
            ['slug' => 'calendar.events.read'],
            [
                'name'        => 'Visualizar eventos do calendário',
                'group'       => 'Calendário',
                'description' => 'Permite consultar eventos e compromissos dos calendários autorizados da organização através do Nodal e da IA.',
            ]
        );
    }

    public function down(): void
    {
        \App\Domain\Permissions\Models\Permission::where('slug', 'calendar.events.read')->delete();
    }
};
