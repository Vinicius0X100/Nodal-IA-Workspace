<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de logs de auditoria — registra todas as ações
     * relevantes para compliance e segurança.
     * Nunca deletar registros — apenas inserir (append-only).
     *
     * Polimórfica: entity_type + entity_id permitem rastrear
     * qualquer entidade do sistema (User, Role, Integration, etc.).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index(); // Ex: "user.created", "role.updated", "integration.connected"
            $table->string('entity_type')->nullable()->index(); // Ex: "App\Domain\Identity\Models\User"
            $table->uuid('entity_id')->nullable();
            $table->json('metadata')->nullable(); // Dados adicionais (old_values, new_values, etc.)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Índice composto para queries comuns
            $table->index(['organization_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
