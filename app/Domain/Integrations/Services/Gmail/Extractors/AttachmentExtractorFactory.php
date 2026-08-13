<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

use App\Domain\Integrations\Exceptions\GoogleGmailException;

class AttachmentExtractorFactory
{
    /**
     * Instancia o extrator correto baseado no Mime Type e extensão do arquivo.
     */
    public static function make(string $mimeType, string $filename): AttachmentExtractorInterface
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // 1. PDF
        if ($mimeType === 'application/pdf' || $extension === 'pdf') {
            return new PdfExtractor();
        }

        // 2. CSV
        if (in_array($mimeType, ['text/csv', 'application/csv']) || $extension === 'csv') {
            return new CsvExtractor();
        }

        // 3. JSON
        if ($mimeType === 'application/json' || $extension === 'json') {
            return new JsonExtractor();
        }

        // 4. HTML
        if ($mimeType === 'text/html' || $extension === 'html' || $extension === 'htm') {
            return new HtmlExtractor();
        }

        // 5. DOCX
        if ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $extension === 'docx') {
            return new DocxExtractor();
        }

        // 6. XLSX
        if ($mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || $extension === 'xlsx') {
            return new XlsxExtractor();
        }

        // 7. Textos genéricos
        if (str_starts_with($mimeType, 'text/') || in_array($extension, ['txt', 'md', 'log'])) {
            return new PlainTextExtractor();
        }

        throw new GoogleGmailException('ATTACHMENT_TYPE_UNSUPPORTED', "O formato do anexo não é suportado para leitura ($mimeType / $extension).");
    }
}
