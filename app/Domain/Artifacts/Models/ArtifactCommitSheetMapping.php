<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;

class ArtifactCommitSheetMapping extends Model
{
    protected $fillable = [
        'artifact_commit_attempt_id',
        'draft_sheet_uuid',
        'provider_sheet_identifier',
    ];

    public function commitAttempt()
    {
        return $this->belongsTo(ArtifactCommitAttempt::class);
    }
}
