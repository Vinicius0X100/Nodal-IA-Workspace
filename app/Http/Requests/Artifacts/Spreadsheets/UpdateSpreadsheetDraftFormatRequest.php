<?php

namespace App\Http\Requests\Artifacts\Spreadsheets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpreadsheetDraftFormatRequest extends FormRequest
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
            'operations' => 'required|array|max:1000',
            'operations.*.type' => 'required|string',
            'operations.*.range' => 'nullable|string',
            'operations.*.format' => 'nullable',
            'operations.*.rows' => 'nullable|integer',
            'operations.*.columns' => 'nullable|integer',
        ];
    }
}
