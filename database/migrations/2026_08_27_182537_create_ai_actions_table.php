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
        Schema::create('ai_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            
            $table->uuid('conversation_id')->nullable();
            
            $table->string('provider')->index(); // e.g., 'meta', 'google'
            $table->string('action_type')->index(); // e.g., 'status.update'
            $table->uuid('target_resource_uuid')->index(); // internal resource uuid
            
            $table->json('prepared_params');
            $table->json('snapshot')->nullable(); // previous state
            
            $table->string('status')->default('pending')->index(); // pending, executed, failed, expired
            
            // Unique index for idempotency within the same integration and target
            $table->string('idempotency_key')->nullable()->index();
            
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            
            $table->json('result_data')->nullable(); // sanitized success response
            $table->json('error_data')->nullable(); // sanitized error response
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
    }
};
