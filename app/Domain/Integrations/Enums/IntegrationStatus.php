<?php

namespace App\Domain\Integrations\Enums;

enum IntegrationStatus: string
{
    case NOT_CONNECTED = 'not_connected';
    case CONNECTED = 'connected';
    case ERROR = 'error';
    case COMING_SOON = 'coming_soon';

    public function label(): string
    {
        return match($this) {
            self::NOT_CONNECTED => 'Não conectado',
            self::CONNECTED => 'Conectado',
            self::ERROR => 'Erro',
            self::COMING_SOON => 'Em breve',
        };
    }
}
