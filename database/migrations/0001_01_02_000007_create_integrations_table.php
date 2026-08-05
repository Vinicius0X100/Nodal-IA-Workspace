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
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // google_workspace, slack, etc
            $table->string('status')->default('not_connected');
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            
            // Uma organização pode ter apenas uma integração de cada provider
            $table->unique(['organization_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
