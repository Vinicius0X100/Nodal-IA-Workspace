<?php

namespace App\Domain\Artifacts\Jobs\Stages;

class StageResult
{
    public bool $isComplete;
    
    public function __construct(bool $isComplete)
    {
        $this->isComplete = $isComplete;
    }
}
