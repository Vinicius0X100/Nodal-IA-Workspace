<?php

namespace App\Domain\Organizations\DTOs;

/**
 * DTO para criação de organização.
 * Garante tipagem forte entre Controller → Action.
 */
final readonly class CreateOrganizationData
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $logo = null,
        public ?array $settings = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'] ?? null,
            logo: $data['logo'] ?? null,
            settings: $data['settings'] ?? null,
        );
    }
}
