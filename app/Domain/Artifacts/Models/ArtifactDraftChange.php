<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtifactDraftChange extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'metadata_json' => 'array',
        'revision' => 'integer',
    ];

    public function artifactDraft(): BelongsTo
    {
        return $this->belongsTo(ArtifactDraft::class);
    }
}
