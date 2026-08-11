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
        Schema::table('integration_configs', function (Blueprint $table) {
            $table->text('delegation_credentials_json')->nullable()->after('configuration_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_configs', function (Blueprint $table) {
            $table->dropColumn('delegation_credentials_json');
        });
    }
};
