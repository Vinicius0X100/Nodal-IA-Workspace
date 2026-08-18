<?php

namespace App\Domain\Identities\Exceptions;

use Exception;

/**
 * Lançada quando uma operação de provider é tentada mas a integração
 * correspondente está desconectada, desabilitada ou inativa no Nodal.
 */
class IntegrationInactiveException extends Exception
{
    protected $code = 'PROVIDER_REAUTH_REQUIRED';
}
