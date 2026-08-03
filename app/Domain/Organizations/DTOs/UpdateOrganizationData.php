<?php

namespace App\Domain\Organizations\DTOs;

final readonly class UpdateOrganizationData
{
    public function __construct(
        public ?string $name = null,
        public ?string $logo = null,
        public ?array $settings = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            logo: $data['logo'] ?? null,
            settings: $data['settings'] ?? null,
        );
    }
}
