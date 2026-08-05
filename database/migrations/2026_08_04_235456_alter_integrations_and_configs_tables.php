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
        Schema::table('integrations', function (Blueprint $table) {
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scope')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_health_check')->nullable();
        });

        Schema::table('integration_configs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_validated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'access_token',
                'refresh_token',
                'token_expires_at',
                'scope',
                'last_sync_at',
                'last_health_check',
            ]);
        });

        Schema::table('integration_configs', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'last_validated_at',
            ]);
        });
    }
};
