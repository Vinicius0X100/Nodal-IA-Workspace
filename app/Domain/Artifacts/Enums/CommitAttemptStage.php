<?php

namespace App\Domain\Artifacts\Enums;

enum CommitAttemptStage: string
{
    case INIT = 'init';
    case PREFLIGHT = 'preflight';
    case CREATE_FILE = 'create_file';
    case WRITE_VALUES = 'write_values';
    case APPLY_FORMATS = 'apply_formats';
    case FINALIZE = 'finalize';
    case CLEANUP = 'cleanup';
}
