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
        Schema::create('async_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // integration_id pode ser nullable pois a abstração é genérica
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('provider')->index(); // meta, google, custom, etc
            $table->string('type')->index();     // insights, performance, etc
            
            $table->string('status')->default('queued')->index(); // queued, running, completed, partial, failed
            $table->unsignedTinyInteger('progress')->default(0);
            
            $table->json('params')->nullable(); // filtros e config pedida
            $table->longText('result')->nullable(); // Guardar JSON gigante (ou path do S3 no futuro)
            
            $table->text('error_message')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['organization_id', 'provider', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('async_reports');
    }
};
