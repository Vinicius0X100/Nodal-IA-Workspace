<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetStructureBatch
{
    /**
     * @param array $sheetsToCreate Array of ['uuid' => string, 'title' => string, 'index' => int]
     * @param array $sheetsToRename Array of ['uuid' => string, 'new_title' => string]
     * @param string|null $firstSheetUuid The draft UUID that corresponds to the initial sheet created
     */
    public function __construct(
        public array $sheetsToCreate,
        public array $sheetsToRename,
        public ?string $firstSheetUuid = null
    ) {}
}
