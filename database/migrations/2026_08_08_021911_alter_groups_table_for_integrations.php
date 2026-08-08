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
        Schema::table('groups', function (Blueprint $table) {
            $table->string('external_id')->nullable()->index();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata_json')->nullable();

            $table->unique(['integration_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['integration_id', 'external_id']);
            $table->dropForeign(['integration_id']);
            $table->dropColumn([
                'external_id',
                'integration_id',
                'provider',
                'email',
                'description',
                'metadata_json'
            ]);
        });
    }
};
