<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

use App\Domain\Integrations\Exceptions\GoogleGmailException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;

class DocxExtractor implements AttachmentExtractorInterface
{
    private const MAX_LENGTH = 100000;

    public function extract(string $binaryData, string $mimeType, string $filename): array
    {
        $tempFile = tmpfile();
        if (!$tempFile) {
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao criar arquivo temporário para leitura DOCX.');
        }

        $metaData = stream_get_meta_data($tempFile);
        $tmpFilename = $metaData['uri'];
        
        fwrite($tempFile, $binaryData);
        
        try {
            // Utiliza o IOFactory para ler o documento DOCX
            $phpWord = IOFactory::load($tmpFilename, 'Word2007');
            
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif ($element instanceof TextRun) {
                        foreach ($element->getElements() as $e) {
                            if ($e instanceof Text) {
                                $text .= $e->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            $text = trim($text);

            if (empty($text)) {
                throw new GoogleGmailException('ATTACHMENT_CONTENT_UNAVAILABLE', 'O documento DOCX parece estar vazio ou não possui texto legível.');
            }

            $truncated = false;
            if (mb_strlen($text) > self::MAX_LENGTH) {
                $text = mb_substr($text, 0, self::MAX_LENGTH) . "\n... [TRUNCATED DOCX CONTENT]";
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
            throw new GoogleGmailException('INTERNAL_ERROR', 'Falha ao realizar parse do arquivo DOCX: ' . $e->getMessage());
        } finally {
            fclose($tempFile); // Remove o arquivo temp
        }
    }
}
