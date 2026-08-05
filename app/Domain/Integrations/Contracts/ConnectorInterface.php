<?php

namespace App\Domain\Integrations\Contracts;

use App\Domain\Organizations\Models\Organization;

interface ConnectorInterface
{
    /**
     * Retorna o nome identificador do provedor (ex: 'google_workspace', 'microsoft_365').
     */
    public function getProviderName(): string;

    /**
     * Inicia o fluxo de conexão (ex: redirecionamento para o OAuth).
     * @param Organization $organization
     * @param array $config (Client ID, Secret, etc.)
     * @return mixed (Pode ser um redirecionamento HTTP, ou uma resposta JSON).
     */
    public function connect(Organization $organization, array $config);

    /**
     * Processa o retorno da conexão (ex: OAuth callback) e salva os tokens.
     * @param Organization $organization
     * @param array $config
     * @param array $requestData (Dados da requisição GET/POST)
     * @return void
     */
    public function handleCallback(Organization $organization, array $config, array $requestData): void;

    /**
     * Desconecta a integração e remove tokens e configurações de conexão.
     */
    public function disconnect(Organization $organization): void;

    /**
     * Tenta atualizar o token de acesso (ex: usando Refresh Token).
     */
    public function refreshToken(Organization $organization): bool;

    /**
     * Retorna o status atual da conexão com o provedor (ex: connected, error, etc).
     */
    public function getStatus(Organization $organization): string;

    /**
     * Verifica se a API do provedor está respondendo corretamente.
     */
    public function health(Organization $organization): bool;
}
