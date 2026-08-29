<?php

namespace App\Domain\Integrations\Services\Meta\Exceptions;

use RuntimeException;

class MetaPermissionDeniedException extends RuntimeException
{
    public function __construct(string $message = 'A integração Meta está conectada, mas não possui permissão para executar esta alteração.')
    {
        parent::__construct($message);
    }
}
