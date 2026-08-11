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
        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // nullable in case it's imported but not yet linked
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index(); // google_workspace, microsoft_365
            $table->string('external_id')->index(); // Google Object ID, Entra ID
            $table->string('primary_email')->index();
            $table->string('display_name')->nullable();
            $table->string('status')->default('linked')->index(); // linked, pending, conflict, disabled
            $table->json('metadata_json')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Uma mesma identidade no provedor só existe uma vez por integração
            $table->unique(['integration_id', 'external_id']);
            
            // Opcional, para garantir que um user nodal não seja linkado 2x no mesmo provider na mesma org
            // mas o user pediu para deixar o sistema robusto sem travas desnecessárias, embora a lógica recomende unique provider per user.
            $table->unique(['user_id', 'integration_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_identities');
    }
};
