<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de configurações — key-value store escopado por organização.
     * Permite configurações flexíveis sem necessidade de novas colunas.
     * O campo type indica como interpretar o value (string, boolean, integer, json).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->timestamps();

            // Cada organização só pode ter uma configuração por chave
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
