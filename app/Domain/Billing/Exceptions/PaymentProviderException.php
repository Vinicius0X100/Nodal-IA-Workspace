<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

class PaymentProviderException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
