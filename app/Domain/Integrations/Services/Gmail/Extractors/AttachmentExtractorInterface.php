<?php

namespace App\Domain\Integrations\Services\Gmail\Extractors;

interface AttachmentExtractorInterface
{
    /**
     * Extrai o conteúdo do binário/texto de um anexo do Gmail.
     * 
     * @param string $binaryData O conteúdo bruto do anexo (já decodificado de base64url).
     * @param string $mimeType O tipo MIME do arquivo (ex: 'application/pdf', 'text/plain').
     * @param string $filename O nome original do arquivo.
     * 
     * @return array Deve retornar um array no formato:
     * [
     *     'type' => 'text'|'table'|'json',
     *     'text' => '...conteúdo...', // Se type == text
     *     'sheets' => [...], // Se type == table (Excel)
     *     'truncated' => bool
     * ]
     * 
     * @throws \App\Domain\Integrations\Exceptions\GoogleGmailException Se o conteúdo não puder ser processado.
     */
    public function extract(string $binaryData, string $mimeType, string $filename): array;
}
