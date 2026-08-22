<?php

namespace App\Domain\AI\Models;

use App\Domain\AI\Enums\MessageRole;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'role' => MessageRole::class,
        'metadata_json' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::USER;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::ASSISTANT;
    }
}
