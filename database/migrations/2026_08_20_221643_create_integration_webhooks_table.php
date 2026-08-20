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
        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->onDelete('cascade');
            $table->string('channel_id')->unique(); // Our UUID
            $table->string('resource_id')->nullable(); // Google's resource ID
            $table->string('resource_uri')->nullable(); // What we are watching
            $table->string('state')->default('active'); // active, expired, error
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_webhooks');
    }
};
