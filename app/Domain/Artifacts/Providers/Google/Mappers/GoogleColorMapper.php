<?php

namespace App\Domain\Artifacts\Providers\Google\Mappers;

class GoogleColorMapper
{
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $c = hexdec($hex);
        return [
            'red' => (($c >> 16) & 0xFF) / 255.0,
            'green' => (($c >> 8) & 0xFF) / 255.0,
            'blue' => ($c & 0xFF) / 255.0,
        ];
    }
}
