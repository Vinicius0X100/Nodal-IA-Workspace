<?php

namespace App\Domain\Artifacts\Enums;

enum CommitAttemptStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
    case COMPLETED = 'completed';
}
