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
        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->string('provider'); // e.g., 'google', 'microsoft', 'internal'
            $table->string('slug'); // unique per organization, e.g., 'google_search_resources'
            $table->string('name');
            $table->text('description')->nullable();
            
            $table->string('endpoint');
            $table->string('http_method')->default('GET');
            $table->string('tool_type')->default('action'); // action, trigger, search
            
            $table->boolean('enabled')->default(true);
            $table->boolean('requires_confirmation')->default(false);
            
            $table->json('configuration_json')->nullable();
            
            $table->timestamps();
            
            $table->unique(['organization_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tools');
    }
};
