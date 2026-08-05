<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de permissões — globais, não escopadas por organização.
     * Cada permissão pertence a um grupo para facilitar
     * a organização na UI (ex: "users", "integrations", "settings").
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // Ex: users, roles, integrations
            $table->string('action'); // Ex: create, read, update, delete
            $table->string('description')->nullable();
            $table->timestamps();

            // Uma permissão é a combinação de módulo e ação (ex: users.create)
            $table->unique(['module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
