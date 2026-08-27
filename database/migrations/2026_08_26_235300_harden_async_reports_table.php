<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('async_reports', function (Blueprint $table) {
            // Assinatura determinística da consulta (tenant-aware: inclui org_id + integration_id)
            $table->string('query_hash', 64)->nullable()->after('type')->index();

            // Rastreabilidade de tentativas e progresso granular
            $table->unsignedTinyInteger('attempts')->default(0)->after('progress');
            $table->unsignedInteger('pages_processed')->default(0)->after('attempts');
            $table->unsignedInteger('records_processed')->default(0)->after('pages_processed');

            // Resultado grande: path no Storage filesystem (private disk por padrão)
            $table->string('result_path', 500)->nullable()->after('result');

            // Expiração do resultado em Storage (independente da expiração do report)
            $table->timestamp('result_expires_at')->nullable()->after('result_path');

            // Expiração do report em si (para cleanup genérico)
            $table->timestamp('expires_at')->nullable()->after('result_expires_at');

            // Observabilidade genérica: duração, rate_limits, etc.
            $table->json('metadata')->nullable()->after('expires_at');

            // Índice composto para buscas de deduplicação (query_hash + org + status)
            $table->index(['organization_id', 'query_hash', 'status'], 'idx_report_dedup');
        });
    }

    public function down(): void
    {
        Schema::table('async_reports', function (Blueprint $table) {
            $table->dropIndex('idx_report_dedup');
            $table->dropIndex(['query_hash']);
            $table->dropColumn([
                'query_hash', 'attempts', 'pages_processed', 'records_processed',
                'result_path', 'result_expires_at', 'expires_at', 'metadata',
            ]);
        });
    }
};
