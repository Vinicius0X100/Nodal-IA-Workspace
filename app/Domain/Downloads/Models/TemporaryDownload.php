<?php

namespace App\Domain\Downloads\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'provider',
        'resource_type',
        'payload',
        'filename',
        'mime_type',
        'size',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
