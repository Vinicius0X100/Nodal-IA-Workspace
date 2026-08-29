<?php

namespace App\Domain\AI\Utils;

class A1Parser
{
    /**
     * Parse A1 notation into GridRange coordinates.
     * Supports:
     * - A1:D10
     * - A:D
     * - 1:3
     * - Sheet1!A1:D10
     * - 'Relatório Mensal'!A1:D10
     * 
     * Returns an array:
     * [
     *   'sheetTitle' => string|null,
     *   'gridRange' => [
     *      'startColumnIndex' => int|null,
     *      'endColumnIndex' => int|null,
     *      'startRowIndex' => int|null,
     *      'endRowIndex' => int|null,
     *   ],
     *   'isBounded' => bool
     * ]
     */
    public static function parse(string $a1): array
    {
        $sheetTitle = null;
        $range = $a1;

        // Check if there is a sheet name
        // E.g., 'Relatório Mensal'!A1:D10 or Sheet1!A1:D10
        if (preg_match('/^(?:\'((?:[^\']|\'\')*)\'|([^!\'"]+))!(.*)$/', $a1, $matches)) {
            $sheetTitle = !empty($matches[1]) ? str_replace("''", "'", $matches[1]) : $matches[2];
            $range = $matches[3];
        }

        $gridRange = [];
        $isBounded = false;

        // Pattern 1: A1:D10
        if (preg_match('/^([A-Z]+)([0-9]+):([A-Z]+)([0-9]+)$/i', $range, $matches)) {
            $gridRange['startColumnIndex'] = self::columnToIndex($matches[1]);
            $gridRange['endColumnIndex'] = self::columnToIndex($matches[3]) + 1;
            $gridRange['startRowIndex'] = ((int) $matches[2]) - 1;
            $gridRange['endRowIndex'] = ((int) $matches[4]);
            $isBounded = true;
        } 
        // Pattern 2: A:D (Columns only)
        elseif (preg_match('/^([A-Z]+):([A-Z]+)$/i', $range, $matches)) {
            $gridRange['startColumnIndex'] = self::columnToIndex($matches[1]);
            $gridRange['endColumnIndex'] = self::columnToIndex($matches[2]) + 1;
            // Rows are omitted
        }
        // Pattern 3: 1:3 (Rows only)
        elseif (preg_match('/^([0-9]+):([0-9]+)$/i', $range, $matches)) {
            $gridRange['startRowIndex'] = ((int) $matches[1]) - 1;
            $gridRange['endRowIndex'] = ((int) $matches[2]);
            // Columns are omitted
        } else {
            throw new \InvalidArgumentException("Range A1 inválido: {$a1}");
        }

        return [
            'sheetTitle' => $sheetTitle,
            'gridRange' => $gridRange,
            'isBounded' => $isBounded
        ];
    }

    private static function columnToIndex(string $column): int
    {
        $column = strtoupper($column);
        $length = strlen($column);
        $index = 0;
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
