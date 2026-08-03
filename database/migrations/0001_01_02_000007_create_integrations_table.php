<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de integrações — registra a conexão de cada organização
     * com serviços externos (Google Workspace, Microsoft 365, etc.).
     * O campo config armazena tokens e metadados de forma encriptada.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('provider')->index();
            $table->enum('status', ['not_connected', 'connected', 'error', 'coming_soon'])->default('not_connected');
            $table->json('config')->nullable(); // Encriptado via cast no Model
            $table->string('connected_by')->nullable(); // Email de quem conectou
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            // Cada organização só pode ter uma integração por provider
            $table->unique(['organization_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
