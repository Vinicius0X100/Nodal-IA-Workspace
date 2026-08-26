<?php

namespace App\Domain\Integrations\Services\Meta;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Value Object responsável por padronizar os períodos de consulta ao Insights da Meta,
 * validando timezones da Ad Account e gerando arrays para o Client.
 */
class MetaInsightsPeriod
{
    private const PRESETS = [
        'today',
        'yesterday',
        'last_7d',
        'last_14d',
        'last_30d',
    ];

    private ?string $preset = null;
    private ?string $since = null;
    private ?string $until = null;
    private ?string $timezone = null;

    public function __construct(string $periodString, ?string $timezone = 'UTC')
    {
        $this->timezone = $timezone ?? 'UTC';

        if (in_array($periodString, self::PRESETS, true)) {
            $this->preset = $periodString;
        } elseif (str_starts_with($periodString, 'custom:')) {
            // custom:YYYY-MM-DD:YYYY-MM-DD
            $parts = explode(':', $periodString);
            if (count($parts) !== 3) {
                throw new InvalidArgumentException("Período customizado inválido. Formato esperado: custom:YYYY-MM-DD:YYYY-MM-DD");
            }
            
            $since = $parts[1];
            $until = $parts[2];

            if (!$this->isValidDate($since) || !$this->isValidDate($until)) {
                throw new InvalidArgumentException("Datas customizadas devem estar no formato YYYY-MM-DD.");
            }

            if (Carbon::parse($since)->gt(Carbon::parse($until))) {
                throw new InvalidArgumentException("A data de início deve ser menor ou igual à data de fim.");
            }

            $this->since = $since;
            $this->until = $until;
        } else {
            throw new InvalidArgumentException("Período não suportado: {$periodString}");
        }
    }

    public function toGraphApiParams(): array
    {
        if ($this->preset) {
            return ['date_preset' => $this->preset];
        }

        return [
            'time_range' => json_encode([
                'since' => $this->since,
                'until' => $this->until
            ])
        ];
    }

    public function getDaysCount(): int
    {
        if ($this->preset === 'today' || $this->preset === 'yesterday') {
            return 1;
        }
        if ($this->preset === 'last_7d') return 7;
        if ($this->preset === 'last_14d') return 14;
        if ($this->preset === 'last_30d') return 30;

        return Carbon::parse($this->since)->diffInDays(Carbon::parse($this->until)) + 1;
    }
    
    public function getHash(): string
    {
        if ($this->preset) {
            return $this->preset;
        }
        return "{$this->since}_{$this->until}";
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
