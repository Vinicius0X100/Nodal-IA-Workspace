<?php

namespace App\Domain\Integrations\Services\Meta;

use RuntimeException;

/**
 * Lançada quando a Meta Graph API retorna um erro de rate limit.
 * Códigos Meta: 17 (Application request limit), 32 (Page request limit), 613 (Calls limit exceeded).
 */
class MetaRateLimitException extends RuntimeException {}
