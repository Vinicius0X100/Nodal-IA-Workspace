<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

class PaymentAlreadyActiveException extends Exception
{
    public function __construct(string $message = "Já existe uma cobrança ativa para esta fatura.", int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
