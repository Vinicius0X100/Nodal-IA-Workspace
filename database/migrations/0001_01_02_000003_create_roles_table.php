<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de papéis (roles) — escopados por organização.
     * Roles de sistema (owner, admin, member) são criados via seeder
     * e marcados com is_system = true para evitar exclusão acidental.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            // Slug deve ser único por organização
            $table->unique(['organization_id', 'slug']);
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
