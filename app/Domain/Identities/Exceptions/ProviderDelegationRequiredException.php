<?php

namespace App\Domain\Identities\Exceptions;

use Exception;

class ProviderDelegationRequiredException extends Exception
{
    protected $code = 'PROVIDER_DELEGATION_REQUIRED';
}
