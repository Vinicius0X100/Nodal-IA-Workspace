<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela pivot entre users e organizations.
     * Define a associação do usuário à organização,
     * incluindo se é owner e quando ingressou.
     */
    public function up(): void
    {
        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Um usuário só pode pertencer uma vez à mesma organização
            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_users');
    }
};
