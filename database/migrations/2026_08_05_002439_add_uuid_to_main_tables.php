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
        $tables = [
            'users',
            'organizations',
            'roles',
            'permissions',
            'integrations',
            'company_verifications',
            'audit_logs',
            'settings',
            'integration_configs',
            'integration_logs'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Adiciona a coluna uuid logo após a coluna id
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'organizations',
            'roles',
            'permissions',
            'integrations',
            'company_verifications',
            'audit_logs',
            'settings',
            'integration_configs',
            'integration_logs'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
