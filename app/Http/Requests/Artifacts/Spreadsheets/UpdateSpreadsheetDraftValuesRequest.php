<?php

namespace App\Http\Requests\Artifacts\Spreadsheets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpreadsheetDraftValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_revision' => 'required|integer|min:1',
            'sheet_uuid' => 'required|uuid',
            'updates' => 'required|array|max:1000',
            'updates.*.range' => 'required|string',
            'updates.*.values' => 'required|array',
            'updates.*.values.*' => 'array',
        ];
    }
}
