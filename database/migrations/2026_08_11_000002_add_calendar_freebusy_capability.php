<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Domain\Permissions\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(
            ['slug' => 'calendar.freebusy.read'],
            [
                'name'        => 'Consultar disponibilidade',
                'group'       => 'Calendário',
                'description' => 'Permite verificar horários livres e ocupados no calendário sem necessariamente acessar detalhes dos eventos.',
            ]
        );
    }

    public function down(): void
    {
        Permission::where('slug', 'calendar.freebusy.read')->delete();
    }
};
