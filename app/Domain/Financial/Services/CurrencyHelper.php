<?php

namespace App\Domain\Financial\Services;

class CurrencyHelper
{
    /**
     * Currencies that use zero decimal places (multiplier 1).
     * Based on ISO 4217 and Meta Graph API documentation.
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF',
        'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF',
        'XOF', 'XPF'
    ];

    /**
     * Currencies that use three decimal places (multiplier 1000).
     */
    private const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'
    ];

    /**
     * Convert a normalized decimal amount to the currency's lowest denomination (subunit).
     * For example:
     * - USD 10.50 -> 1050 (cents)
     * - JPY 100 -> 100
     * - TND 10.50 -> 10500 (millimes)
     *
     * @param float $amount Normalized decimal amount.
     * @param string $currency 3-letter currency code (e.g., 'USD').
     * @return int Amount in lowest denomination.
     */
    public static function toLowestDenomination(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $multiplier = self::getMultiplier($currency);

        // Use round to avoid floating point precision issues (e.g., 10.5 * 100 = 1050.00000001)
        return (int) round($amount * $multiplier);
    }

    /**
     * Convert an amount in the currency's lowest denomination (subunit) to a normalized decimal.
     * For example:
     * - USD 1050 (cents) -> 10.50
     * - JPY 100 -> 100.00
     * - TND 10500 (millimes) -> 10.50
     *
     * @param int|float $amount Amount in lowest denomination.
     * @param string $currency 3-letter currency code (e.g., 'USD').
     * @return float Normalized decimal amount.
     */
    public static function toDecimal(int|float $amount, string $currency): float
    {
        $currency = strtoupper($currency);
        $multiplier = self::getMultiplier($currency);

        return round((float) $amount / $multiplier, 3); // using 3 for precision safety, though typically 2
    }

    /**
     * Minimum valid amount in normalized decimal for a given currency based on Meta limits.
     * By default, Meta requires at least $1 USD equivalent. For simplicity, we enforce
     * 1 major unit for most currencies, except for zero-decimal ones.
     */
    public static function getMinimumDecimalAmount(string $currency): float
    {
        $currency = strtoupper($currency);

        // A simple fallback rule (this can be configured properly in ai_guardrails if needed)
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES)) {
            return 100.0; // 100 JPY
        }

        return 1.0; // 1 USD, 1 BRL, 1 EUR
    }

    private static function getMultiplier(string $currency): int
    {
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES)) {
            return 1;
        }

        if (in_array($currency, self::THREE_DECIMAL_CURRENCIES)) {
            return 1000;
        }

        // Default for most currencies (USD, BRL, EUR, GBP, etc.)
        return 100;
    }
}
