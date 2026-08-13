<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

class PlainTextExtractor implements AttachmentExtractorInterface
{
    private const MAX_LENGTH = 100000; // ~100k caracteres

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        $text = mb_convert_encoding($binaryData, 'UTF-8', 'UTF-8');
        
        $truncated = false;
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_LENGTH) . "\n... [TRUNCATED]";
            $truncated = true;
        }

        return [
            'type' => 'text',
            'text' => $text,
            'truncated' => $truncated,
        ];
    }
}
