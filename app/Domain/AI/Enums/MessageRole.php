<?php

namespace App\Domain\AI\Enums;

enum MessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
    case TOOL = 'tool';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Usuário',
            self::ASSISTANT => 'Assistente',
            self::SYSTEM => 'Sistema',
            self::TOOL => 'Ferramenta',
        };
    }
}
