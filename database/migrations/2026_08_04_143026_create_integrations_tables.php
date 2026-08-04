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
        Schema::dropIfExists('integrations');

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // ex: google_workspace, slack, github
            $table->string('status')->default('not_connected'); // not_connected, configuring, connected, error, coming_soon
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            // Uma organização só pode ter uma integração do mesmo tipo (provider) no momento.
            $table->unique(['organization_id', 'provider']);
        });

        Schema::create('integration_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable(); // criptografado
            $table->string('redirect_uri')->nullable();
            $table->string('tenant')->nullable(); // ex: domínio do google workspace
            $table->json('configuration_json')->nullable(); // outras configurações flexíveis
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('status'); // success, error, warning, info
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_configs');
        Schema::dropIfExists('integrations');
    }
};
