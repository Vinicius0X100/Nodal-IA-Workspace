<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

use App\Domain\Integrations\Exceptions\GoogleGmailException;

class CsvExtractor implements AttachmentExtractorInterface
{
    private const MAX_ROWS = 200;
    private const MAX_COLS = 30;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        // Tratamento de encoding caso venha do Excel em Windows (iso-8859-1) ou Mac
        $encoding = mb_detect_encoding($binaryData, 'UTF-8, ISO-8859-1, Windows-1252', true);
        if ($encoding && $encoding !== 'UTF-8') {
            $binaryData = mb_convert_encoding($binaryData, 'UTF-8', $encoding);
        }

        $tempFile = tmpfile();
        if (!$tempFile) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao criar arquivo temporário para leitura de CSV.');
        }

        fwrite($tempFile, $binaryData);
        rewind($tempFile);

        // Tenta inferir delimitador
        $firstLine = fgets($tempFile);
        rewind($tempFile);
        
        $delimiter = ',';
        if ($firstLine) {
            $semicolons = substr_count($firstLine, ';');
            $commas = substr_count($firstLine, ',');
            if ($semicolons > $commas) {
                $delimiter = ';';
            }
        }

        $rows = [];
        $truncated = false;
        $rowCount = 0;

        while (($data = fgetcsv($tempFile, null, $delimiter)) !== false) {
            if ($rowCount >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            // Trunca as colunas se passar do máximo
            if (count($data) > self::MAX_COLS) {
                $data = array_slice($data, 0, self::MAX_COLS);
                $truncated = true;
            }

            $rows[] = $data;
            $rowCount++;
        }

        fclose($tempFile);

        return [
            'type' => 'table',
            'sheets' => [
                [
                    'name' => 'CSV Data',
                    'rows' => $rows,
                ]
            ],
            'truncated' => $truncated,
        ];
    }
}
