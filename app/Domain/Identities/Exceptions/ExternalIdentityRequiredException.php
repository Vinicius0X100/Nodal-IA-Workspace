<?php

namespace App\Domain\Identities\Exceptions;

use Exception;

class ExternalIdentityRequiredException extends Exception
{
    protected $code = 'EXTERNAL_IDENTITY_REQUIRED';
}
