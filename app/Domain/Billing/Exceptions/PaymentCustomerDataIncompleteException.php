<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

class PaymentCustomerDataIncompleteException extends Exception
{
    public function __construct(
        string $message = "PAYMENT_CUSTOMER_DATA_INCOMPLETE",
        public readonly array $missingFields = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
