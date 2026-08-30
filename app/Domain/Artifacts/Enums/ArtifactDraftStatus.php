<?php

namespace App\Domain\Artifacts\Enums;

enum ArtifactDraftStatus: string
{
    case DRAFT = 'draft';
    case COMMITTING = 'committing';
    case COMMITTED = 'committed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
