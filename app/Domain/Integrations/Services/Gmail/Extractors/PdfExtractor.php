<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

use App\Domain\Integrations\Exceptions\GoogleGmailException;
use Smalot\PdfParser\Parser;

class PdfExtractor implements AttachmentExtractorInterface
{
    private const MAX_LENGTH = 100000;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseContent($binaryData);
            
            $text = $pdf->getText();
            $text = trim($text);

            if (empty($text)) {
                // PDF sem texto, provavelmente escaneado
                throw new GoogleGmailException('ATTACHMENT_CONTENT_UNAVAILABLE', 'O PDF não contém camada de texto legível (pode ser uma imagem escaneada). OCR automático não suportado.');
            }

            $truncated = false;
            if (mb_strlen($text) > self::MAX_LENGTH) {
                $text = mb_substr($text, 0, self::MAX_LENGTH) . "\n... [TRUNCATED PDF CONTENT]";
                $truncated = true;
            }

            return [
                'type' => 'text',
                'text' => $text,
                'truncated' => $truncated,
            ];
        } catch (GoogleGmailException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao realizar parse do arquivo PDF: ' . $e->getMessage());
        }
    }
}
