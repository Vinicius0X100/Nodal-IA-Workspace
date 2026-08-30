<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpreadsheetDraftSheet extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'properties_json' => 'array',
        'dimensions_json' => 'array',
        'index' => 'integer',
    ];

    public function artifactDraft(): BelongsTo
    {
        return $this->belongsTo(ArtifactDraft::class);
    }
    
    public function chunks(): HasMany
    {
        return $this->hasMany(SpreadsheetDraftChunk::class, 'sheet_id');
    }
    
    public function formats(): HasMany
    {
        return $this->hasMany(SpreadsheetDraftFormat::class, 'sheet_id');
    }
    
    public function merges(): HasMany
    {
        return $this->hasMany(SpreadsheetDraftMerge::class, 'sheet_id');
    }
}
