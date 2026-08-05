<?php

namespace App\Domain\Integrations\Services;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class GoogleOAuthService
{
    /**
     * Monta o provider dinamicamente, já que as credenciais vêm do banco e não do services.php
     */
    public function buildProvider(string $clientId, string $clientSecret, string $redirectUri): GoogleProvider
    {
        // Precisamos instanciar manualmente o provedor Google do Socialite, 
        // pois ele normalmente puxa do config() nativamente.
        return Socialite::buildProvider(
            GoogleProvider::class,
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect' => $redirectUri,
            ]
        );
    }
}
