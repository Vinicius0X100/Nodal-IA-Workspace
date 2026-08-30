<?php

namespace App\Domain\Artifacts\Models;

use App\Domain\Artifacts\Enums\ArtifactDraftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArtifactDraft extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'status' => ArtifactDraftStatus::class,
        'schema_version' => 'integer',
        'revision' => 'integer',
    ];

    public function sheets(): HasMany
    {
        return $this->hasMany(SpreadsheetDraftSheet::class);
    }
    
    public function commitAttempts(): HasMany
    {
        return $this->hasMany(ArtifactCommitAttempt::class);
    }
}
