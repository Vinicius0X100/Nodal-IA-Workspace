<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Services\Meta\Enums\MetaErrorType;

class MetaGraphErrorClassifier
{
    /**
     * Classifica o erro retornado pela Graph API em um tipo enum interno.
     *
     * @param int $httpStatus
     * @param array $errorBody Bloco 'error' extraído do response JSON
     * @return MetaErrorType
     */
    public function classify(int $httpStatus, array $errorBody): MetaErrorType
    {
        $errorCode = (int) ($errorBody['code'] ?? 0);
        $errorSubcode = (int) ($errorBody['error_subcode'] ?? 0);

        // 1. TOKEN_INVALID (Exige reconexão)
        // 190 = OAuthException (Token expired, changed password, etc)
        // 102 = Session key invalid
        if ($errorCode === 190 || $errorCode === 102) {
            return MetaErrorType::TOKEN_INVALID;
        }

        // 2. PERMISSION_DENIED (Mantém integração ativa, mas falha a ação)
        // 10 = Application does not have permission
        // 200 - 299 = Permission errors (e.g. 200: Permissions error, 270: Resource is read-only)
        if ($errorCode === 10 || ($errorCode >= 200 && $errorCode <= 299)) {
            return MetaErrorType::PERMISSION_DENIED;
        }

        // 3. RATE_LIMITED (Exige retry com backoff)
        // 4 = Application request limit reached
        // 17 = User request limit reached
        // 32 = Page request limit reached
        // 613 = Custom calls per session limit reached
        if (in_array($errorCode, [4, 17, 32, 613], true)) {
            return MetaErrorType::RATE_LIMITED;
        }

        // 4. INVALID_REQUEST (Erro do cliente: param inválido, limite financeiro estourado, etc)
        // 100 = Invalid parameter
        if ($errorCode === 100 || $httpStatus === 400) {
            return MetaErrorType::INVALID_REQUEST;
        }

        // 5. PROVIDER_ERROR (Falha genérica da Meta)
        // 1 = Unknown error
        // 2 = Service temporarily unavailable
        return MetaErrorType::PROVIDER_ERROR;
    }
}
