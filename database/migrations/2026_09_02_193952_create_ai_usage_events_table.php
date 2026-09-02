<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant — obrigatório
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();

            // Fonte do consumo
            $table->string('provider', 50);             // google, openai, anthropic
            $table->string('model', 100);               // gemini-3.5-flash
            $table->string('operation', 100);           // assistant_chat, tool_call, etc.
            $table->string('source', 100)->nullable();  // main_agent, document_analysis, etc.
            $table->string('request_uuid', 36)->nullable();
            $table->string('n8n_execution_id', 100)->nullable();

            // Tokens — como recebidos do provider
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('thinking_tokens')->default(0);
            $table->unsignedInteger('tool_use_prompt_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Custo calculado pelo Laravel (nunca pelo n8n)
            $table->decimal('provider_cost_usd', 14, 8)->default(0);
            $table->decimal('exchange_rate', 10, 6)->default(0);
            $table->decimal('provider_cost_brl', 14, 8)->default(0);

            // Custo comercial de referência para créditos Nodal
            // (pode ser diferente do custo real com fx_buffer)
            $table->decimal('commercial_reference_cost_brl', 14, 8)->default(0);

            // Créditos Nodal — 1 crédito = R$ 0,01 de custo-base
            // NUNCA arredondar por chamada individual
            $table->decimal('credits_used', 18, 8)->default(0);

            // Faturável?
            $table->boolean('billable')->default(true);

            // Categoria para análise de margem
            // user_request, agent_reasoning, document_analysis, tool_processing,
            // internal_retry, system_operation, adjustment
            $table->string('billing_category', 50)->default('user_request');

            // FKs para rate e câmbio usados no cálculo
            $table->unsignedBigInteger('model_rate_id')->nullable();
            $table->unsignedBigInteger('exchange_rate_id')->nullable();

            // Idempotência — evita duplicação por retry do n8n
            $table->string('idempotency_key', 128)->unique();

            // Dados brutos do provider para auditoria
            $table->json('provider_usage_json')->nullable();
            $table->json('metadata_json')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — ledger imutável

            // Performance indices
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['organization_id', 'user_id', 'occurred_at']);
            $table->index(['organization_id', 'billing_category']);
            $table->index(['provider', 'model']);
            $table->index(['organization_id', 'billable', 'occurred_at']);

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->foreign('model_rate_id')->references('id')->on('ai_model_rates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
