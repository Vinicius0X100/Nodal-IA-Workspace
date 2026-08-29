<?php

namespace App\Domain\AI\Contracts;

final class AIChatResult
{
    /**
     * @param string $content Texto da resposta em Markdown
     * @param array $artifacts Array de artefatos estruturados gerados na interação (planilhas, documentos)
     */
    public function __construct(
        public readonly string $content,
        public readonly array $artifacts = []
    ) {}
}
