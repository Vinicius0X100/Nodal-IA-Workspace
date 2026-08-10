<?php

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

class GoogleCalendarException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = ''
    ) {
        parent::__construct($message ?: $errorCode);
    }
}
