<?php

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

class GoogleGmailException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly array $additionalData = []
    ) {
        parent::__construct($message ?: $errorCode);
    }
}
