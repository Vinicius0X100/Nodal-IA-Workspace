<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class AIMetaInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita no Controller via AuthorizationService
    }

    public function rules(): array
    {
        return [
            'resource_uuid' => 'required_without:resource_uuids|uuid',
            'resource_uuids' => 'required_without:resource_uuid|array|min:1|max:10',
            'resource_uuids.*' => 'uuid',
            'level' => 'required|string|in:account,campaign,adset,ad',
            'period' => 'required_without:date_from|prohibits:date_from,date_to|string|in:today,yesterday,last_7d,last_14d,last_30d',
            'date_from' => 'required_without:period|required_with:date_to|prohibits:period|date_format:Y-m-d',
            'date_to' => 'required_without:period|required_with:date_from|prohibits:period|date_format:Y-m-d|after_or_equal:date_from',
        ];
    }

    public function messages(): array
    {
        return [
            'resource_uuid.required_without' => 'resource_uuid é obrigatório quando resource_uuids não é informado.',
            'resource_uuids.required_without' => 'resource_uuids é obrigatório quando resource_uuid não é informado.',
            'resource_uuids.max' => 'Máximo de 10 resources por consulta.',
            'level.in' => 'Level deve ser: account, campaign, adset ou ad.',
            'period.in' => 'Period deve ser: today, yesterday, last_7d, last_14d ou last_30d.',
            'period.prohibits' => 'O parâmetro period não pode ser enviado junto com date_from ou date_to.',
            'date_from.required_with' => 'date_from é obrigatório quando date_to é informado.',
            'date_to.required_with' => 'date_to é obrigatório quando date_from é informado.',
            'date_from.required_without' => 'date_from é obrigatório quando period não é informado.',
            'date_to.required_without' => 'date_to é obrigatório quando period não é informado.',
            'date_from.prohibits' => 'Datas customizadas não podem ser enviadas junto com period.',
            'date_to.prohibits' => 'Datas customizadas não podem ser enviadas junto com period.',
            'date_to.after_or_equal' => 'date_to deve ser igual ou posterior a date_from.',
        ];
    }
}
