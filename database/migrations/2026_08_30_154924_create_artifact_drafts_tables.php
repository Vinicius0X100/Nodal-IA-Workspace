<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('conversation_uuid')->nullable()->index();
            
            $table->string('type')->index();
            $table->string('title')->nullable();
            $table->string('status')->default('draft')->index(); // draft, committing, committed, failed, cancelled
            
            $table->integer('schema_version')->default(1);
            $table->integer('revision')->default(1);
            
            $table->uuid('committed_resource_uuid')->nullable()->index();
            
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
        });

        Schema::create('artifact_draft_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('artifact_draft_id')->constrained('artifact_drafts')->cascadeOnDelete();
            $table->integer('revision');
            
            $table->uuid('sheet_uuid')->nullable()->index();
            $table->string('type'); // values_updated, cells_cleared, format_applied, etc
            $table->string('range')->nullable();
            $table->jsonb('metadata_json')->nullable();
            
            $table->timestamps();
            
            $table->unique(['artifact_draft_id', 'revision', 'type', 'range'], 'draft_changes_unique_idx');
        });

        Schema::create('spreadsheet_draft_sheets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('artifact_draft_id')->constrained('artifact_drafts')->cascadeOnDelete();
            
            $table->integer('index');
            $table->string('title');
            
            $table->jsonb('properties_json')->nullable(); // freeze rows/cols
            $table->jsonb('dimensions_json')->nullable(); // widths/heights
            
            $table->timestamps();
            
            $table->unique(['artifact_draft_id', 'index']);
            $table->unique(['artifact_draft_id', 'title']);
        });

        Schema::create('spreadsheet_draft_formats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sheet_id')->constrained('spreadsheet_draft_sheets')->cascadeOnDelete();
            
            $table->integer('revision');
            $table->integer('operation_index');
            
            $table->integer('start_row');
            $table->integer('end_row')->nullable();
            $table->integer('start_col');
            $table->integer('end_col')->nullable();
            
            $table->jsonb('format_json');
            
            $table->timestamps();
            
            $table->unique(['sheet_id', 'revision', 'operation_index'], 'format_precedence_unique');
            $table->index(['sheet_id', 'start_row', 'end_row', 'start_col', 'end_col'], 'format_range_idx');
        });

        Schema::create('spreadsheet_draft_merges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sheet_id')->constrained('spreadsheet_draft_sheets')->cascadeOnDelete();
            
            $table->integer('start_row');
            $table->integer('end_row');
            $table->integer('start_col');
            $table->integer('end_col');
            
            $table->timestamps();
            
            $table->index(['sheet_id', 'start_row', 'start_col'], 'merge_range_idx');
        });

        Schema::create('spreadsheet_draft_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sheet_id')->constrained('spreadsheet_draft_sheets')->cascadeOnDelete();
            
            $table->integer('chunk_row');
            $table->integer('chunk_column');
            
            $table->jsonb('payload_json');
            $table->integer('revision')->default(1);
            
            $table->timestamps();
            
            $table->unique(['sheet_id', 'chunk_row', 'chunk_column']);
        });

        Schema::create('artifact_commit_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('commit_uuid')->unique();
            $table->foreignId('artifact_draft_id')->constrained('artifact_drafts')->cascadeOnDelete();
            $table->integer('source_revision');
            
            $table->string('provider');
            $table->string('status')->default('pending')->index(); 
            $table->string('provider_external_id')->nullable(); 
            
            $table->string('current_stage')->default('init');
            $table->uuid('current_sheet_uuid')->nullable();
            $table->string('current_chunk_key')->nullable();
            
            $table->string('last_error_code')->nullable();
            $table->integer('attempt_number')->default(1);
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            $table->index(['artifact_draft_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_commit_attempts');
        Schema::dropIfExists('spreadsheet_draft_chunks');
        Schema::dropIfExists('spreadsheet_draft_merges');
        Schema::dropIfExists('spreadsheet_draft_formats');
        Schema::dropIfExists('spreadsheet_draft_sheets');
        Schema::dropIfExists('artifact_draft_changes');
        Schema::dropIfExists('artifact_drafts');
    }
};
