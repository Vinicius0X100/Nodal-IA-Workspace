<?php

namespace App\Domain\AI\Models;

use App\Domain\AI\Enums\ConversationStatus;
use App\Domain\AI\Enums\MessageRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ConversationStatus::class,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest()->limit(1);
    }

    /**
     * Retorna o título exibível da conversa.
     */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: 'Nova Conversa';
    }
}
