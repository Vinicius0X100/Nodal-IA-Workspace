<?php

namespace App\Domain\Integrations\Services\Meta\Enums;

enum MetaErrorType: string
{
    case TOKEN_INVALID = 'TOKEN_INVALID';
    case PERMISSION_DENIED = 'PERMISSION_DENIED';
    case INVALID_REQUEST = 'INVALID_REQUEST';
    case RATE_LIMITED = 'RATE_LIMITED';
    case PROVIDER_ERROR = 'PROVIDER_ERROR';
}
