<?php

namespace App\Domain\Resources\Enums;

enum Provider: string
{
    case GOOGLE_WORKSPACE = 'google_workspace';
    case META = 'meta';

    public function label(): string
    {
        return match ($this) {
            self::GOOGLE_WORKSPACE => 'Google Workspace',
            self::META             => 'Meta',
        };
    }
}
