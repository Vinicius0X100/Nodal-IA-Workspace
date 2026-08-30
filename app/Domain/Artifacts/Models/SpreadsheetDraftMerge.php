<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpreadsheetDraftMerge extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'start_row' => 'integer',
        'end_row' => 'integer',
        'start_col' => 'integer',
        'end_col' => 'integer',
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetDraftSheet::class, 'sheet_id');
    }
}
