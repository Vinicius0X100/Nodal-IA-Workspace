<?php

namespace App\Domain\Artifacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpreadsheetDraftFormat extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'format_json' => 'array',
        'revision' => 'integer',
        'operation_index' => 'integer',
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
