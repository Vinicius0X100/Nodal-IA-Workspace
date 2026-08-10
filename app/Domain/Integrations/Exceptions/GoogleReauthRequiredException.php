<?php

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

/**
 * Lançada quando o token Google é inválido/revogado e a integração precisa ser reconectada.
 * O controller deve retornar HTTP 503 com código GOOGLE_REAUTH_REQUIRED.
 */
class GoogleReauthRequiredException extends RuntimeException {}
