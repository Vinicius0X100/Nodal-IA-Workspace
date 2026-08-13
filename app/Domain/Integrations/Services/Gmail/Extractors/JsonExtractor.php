<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

class JsonExtractor implements AttachmentExtractorInterface
{
    private const MAX_LENGTH = 100000;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        $text = mb_convert_encoding($binaryData, 'UTF-8', 'UTF-8');
        
        // Verifica se é um JSON válido.
        $decoded = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Re-encoda bonito para a IA ler de forma estruturada.
            // Ignora opções complexas para não travar
            $text = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $truncated = false;
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_LENGTH) . "\n... [TRUNCATED JSON]";
            $truncated = true;
        }

        return [
            'type' => 'json',
            'text' => $text,
            'truncated' => $truncated,
        ];
    }
}
