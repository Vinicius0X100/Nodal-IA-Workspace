<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

class HtmlExtractor implements AttachmentExtractorInterface
{
    private const MAX_LENGTH = 100000;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        $text = mb_convert_encoding($binaryData, 'UTF-8', 'UTF-8');
        
        // Remove conteúdo de scripts e styles usando regex
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        
        // Remove tags HTML deixando apenas o texto bruto
        $text = strip_tags($text);
        
        // Remove quebras de linha excessivas e limpa espaços
        $text = preg_replace("/[\r\n]+/", "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = trim($text);

        $truncated = false;
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_LENGTH) . "\n... [TRUNCATED HTML]";
            $truncated = true;
        }

        return [
            'type' => 'text',
            'text' => $text,
            'truncated' => $truncated,
        ];
    }
}
