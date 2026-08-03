<?php

namespace App\Domain\Integrations\Enums;

enum IntegrationProvider: string
{
    case GOOGLE_WORKSPACE = 'google_workspace';
    case MICROSOFT_365 = 'microsoft_365';
    case SLACK = 'slack';
    case GITHUB = 'github';
    case HUBSPOT = 'hubspot';

    public function label(): string
    {
        return match($this) {
            self::GOOGLE_WORKSPACE => 'Google Workspace',
            self::MICROSOFT_365 => 'Microsoft 365',
            self::SLACK => 'Slack',
            self::GITHUB => 'GitHub',
            self::HUBSPOT => 'HubSpot',
        };
    }
}
