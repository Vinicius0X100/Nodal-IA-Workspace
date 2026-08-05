<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->text('access_token'); // Criptografado
            $table->text('refresh_token')->nullable(); // Criptografado
            $table->timestamp('expires_at')->nullable();
            $table->text('scope')->nullable();
            $table->string('token_type')->nullable(); // ex: Bearer
            $table->timestamps();

            // Apenas 1 token ativo por provedor/organização (se for o design desejado)
            $table->unique(['organization_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
    }
};
