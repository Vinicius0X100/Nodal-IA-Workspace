<?php

namespace App\Domain\Artifacts\Providers\Google\Mappers;

class GoogleNumberFormatMapper
{
    private static array $presetPatterns = [
        'CURRENCY_BRL' => ['type' => 'CURRENCY', 'pattern' => '"R$"#,##0.00'],
        'CURRENCY_USD' => ['type' => 'CURRENCY', 'pattern' => '"$"#,##0.00'],
        'INTEGER' => ['type' => 'NUMBER', 'pattern' => '#,##0'],
        'DECIMAL_2' => ['type' => 'NUMBER', 'pattern' => '#,##0.00'],
        'PERCENT' => ['type' => 'PERCENT', 'pattern' => '0.00%'],
        'DATE_DMY' => ['type' => 'DATE', 'pattern' => 'dd/mm/yyyy'],
        'DATE_YMD' => ['type' => 'DATE', 'pattern' => 'yyyy-mm-dd'],
        'DATETIME_DMY' => ['type' => 'DATE_TIME', 'pattern' => 'dd/mm/yyyy hh:mm:ss']
    ];

    public static function getPreset(string $formatKey): ?array
    {
        return self::$presetPatterns[$formatKey] ?? null;
    }
}
