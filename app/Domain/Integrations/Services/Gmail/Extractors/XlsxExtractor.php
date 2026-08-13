<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

use App\Domain\Integrations\Exceptions\GoogleGmailException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XlsxExtractor implements AttachmentExtractorInterface
{
    private const MAX_ROWS = 200;
    private const MAX_COLS = 30;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        $tempFile = tmpfile();
        if (!$tempFile) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao criar arquivo temporário para leitura XLSX.');
        }

        $metaData = stream_get_meta_data($tempFile);
        $tmpFilename = $metaData['uri'];
        
        fwrite($tempFile, $binaryData);
        
        try {
            $spreadsheet = IOFactory::load($tmpFilename);
            
            $sheetsData = [];
            $globalTruncated = false;

            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $rows = [];
                $rowCount = 0;
                
                foreach ($worksheet->getRowIterator() as $row) {
                    if ($rowCount >= self::MAX_ROWS) {
                        $globalTruncated = true;
                        break;
                    }

                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);

                    $rowData = [];
                    $colCount = 0;
                    foreach ($cellIterator as $cell) {
                        if ($colCount >= self::MAX_COLS) {
                            $globalTruncated = true;
                            break;
                        }
                        
                        $value = $cell->getValue();
                        $rowData[] = $value !== null ? (string)$value : '';
                        $colCount++;
                    }

                    // Ignorar linhas 100% vazias para economizar tokens
                    if (count(array_filter($rowData, fn($v) => trim($v) !== '')) > 0) {
                        $rows[] = $rowData;
                        $rowCount++;
                    }
                }

                $sheetsData[] = [
                    'name' => $worksheet->getTitle(),
                    'rows' => $rows,
                ];
            }

            return [
                'type' => 'table',
                'sheets' => $sheetsData,
                'truncated' => $globalTruncated,
            ];
        } catch (\Exception $e) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao realizar parse do arquivo XLSX: ' . $e->getMessage());
        } finally {
            fclose($tempFile);
        }
    }
}
