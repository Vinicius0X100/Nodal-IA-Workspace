<?php

namespace App\Domain\Artifacts\Models;

use App\Domain\Artifacts\Enums\CommitAttemptStage;
use App\Domain\Artifacts\Enums\CommitAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtifactCommitAttempt extends Model
{
    protected $fillable = [
        'commit_uuid',
        'artifact_draft_id',
        'source_revision',
        'provider',
        'status',
        'current_stage',
        'provider_external_id',
        'checkpoint_json',
        'error_payload',
        'attempt_number',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'checkpoint_json' => 'array',
        'error_payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function artifactDraft()
    {
        return $this->belongsTo(ArtifactDraft::class);
    }

    public function sheetMappings()
    {
        return $this->hasMany(ArtifactCommitSheetMapping::class);
    }
}
