<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class AIMetaCampaignsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita no Controller via AuthorizationService
    }

    public function rules(): array
    {
        return [
            'ad_account_uuid' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:ACTIVE,PAUSED,ARCHIVED',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
