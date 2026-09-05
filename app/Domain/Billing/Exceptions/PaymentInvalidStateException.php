<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

class PaymentInvalidStateException extends Exception
{
    public function __construct(string $message = "A fatura não está em estado válido para esta operação.", int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
