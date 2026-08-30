<?php

namespace App\Domain\Artifacts\Models;

use App\Domain\Artifacts\Enums\CommitAttemptStage;
use App\Domain\Artifacts\Enums\CommitAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtifactCommitAttempt extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'status' => CommitAttemptStatus::class,
        'current_stage' => CommitAttemptStage::class,
        'source_revision' => 'integer',
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function artifactDraft(): BelongsTo
    {
        return $this->belongsTo(ArtifactDraft::class);
    }
}
