<?php

namespace App\Domain\Identities\Exceptions;

use Exception;

class TargetIdentityNotFoundException extends Exception
{
    protected $code = 'TARGET_IDENTITY_NOT_FOUND';
}
