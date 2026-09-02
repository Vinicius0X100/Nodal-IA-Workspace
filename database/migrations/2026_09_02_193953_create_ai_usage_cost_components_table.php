<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_cost_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_usage_event_id');

            // input_tokens, output_tokens, thinking_tokens, cached_tokens,
            // cache_storage, grounding_search, image_generation, audio, video, embedding
            $table->string('component_type', 50);

            $table->decimal('quantity', 18, 6)->default(0);
            $table->string('unit', 30)->default('tokens'); // tokens, requests, seconds, etc.

            // Rate por unidade
            $table->decimal('rate', 14, 8)->default(0);
            $table->string('currency', 3)->default('USD');

            // Custo calculado deste componente
            $table->decimal('cost', 14, 8)->default(0);

            $table->json('metadata_json')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ai_usage_event_id')->references('id')->on('ai_usage_events')->onDelete('cascade');
            $table->index(['ai_usage_event_id', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_cost_components');
    }
};
