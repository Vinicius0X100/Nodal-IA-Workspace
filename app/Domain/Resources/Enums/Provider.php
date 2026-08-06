<?php

namespace App\Domain\Resources\Enums;

enum Provider: string
{
    case GOOGLE_WORKSPACE = 'google_workspace';

    public function label(): string
    {
        return match ($this) {
            self::GOOGLE_WORKSPACE => 'Google Workspace',
        };
    }
}
