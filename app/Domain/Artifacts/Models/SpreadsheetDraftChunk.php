<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpreadsheetDraftChunk extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'payload_json' => 'array',
        'chunk_row' => 'integer',
        'chunk_column' => 'integer',
        'revision' => 'integer',
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetDraftSheet::class, 'sheet_id');
    }
}
