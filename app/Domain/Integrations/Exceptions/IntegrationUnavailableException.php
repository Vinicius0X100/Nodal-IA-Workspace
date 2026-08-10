<?php

namespace App\Domain\Integrations\Exceptions;

use RuntimeException;

/** Lançada quando a integração Google Workspace não está conectada/ativa. */
class IntegrationUnavailableException extends RuntimeException {}
