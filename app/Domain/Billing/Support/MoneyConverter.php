<?php

namespace App\Domain\Billing\Support;

class MoneyConverter
{
    /**
     * Converte valor decimal vindo de API externa (ex: 1990.50) para centavos inteiros (199050).
     * Evita problemas de arredondamento de ponto flutuante em comparações financeiras.
     */
    public static function toCents(float|int|string|null $decimalAmount): int
    {
        if ($decimalAmount === null || $decimalAmount === '') {
            return 0;
        }

        return (int) round(((float) $decimalAmount) * 100);
    }

    /**
     * Converte centavos inteiros (199050) para valor float com 2 casas decimais (1990.50).
     */
    public static function toDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Formata centavos em string no padrão brasileiro (R$ 1.990,50).
     */
    public static function toBrlString(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}
