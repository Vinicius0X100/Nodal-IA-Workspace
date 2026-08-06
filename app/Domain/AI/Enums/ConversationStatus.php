<?php

namespace App\Domain\AI\Enums;

enum ConversationStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::ARCHIVED => 'Arquivada',
        };
    }
}
