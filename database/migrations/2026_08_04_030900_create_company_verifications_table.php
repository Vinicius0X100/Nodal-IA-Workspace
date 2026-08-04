<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Dados da Empresa
            $table->string('company_name');        // Razão Social
            $table->string('trade_name')->nullable(); // Nome Fantasia
            $table->string('cnpj', 18);
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();

            // Responsável
            $table->string('responsible_name');
            $table->string('responsible_position');
            $table->string('corporate_email');
            $table->string('phone', 20)->nullable();

            // Documentação
            $table->enum('document_type', ['cnpj_card', 'social_contract', 'ccmei'])->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();

            // Declaração
            $table->boolean('declaration_accepted')->default(false);

            // Verificação
            $table->enum('verification_status', ['pending', 'under_review', 'verified', 'rejected'])->default('pending');
            $table->text('review_notes')->nullable(); // motivo da reprovação
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_verifications');
    }
};
