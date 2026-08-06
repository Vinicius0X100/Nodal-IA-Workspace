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
        Schema::create('integration_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('resource_type')->index();
            $table->string('external_id')->index();
            $table->string('parent_external_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->boolean('is_folder')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('created_by_provider_at')->nullable();
            $table->timestamp('updated_by_provider_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_resources');
    }
};
