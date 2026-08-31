<?php

namespace App\Http\Requests\Artifacts\Spreadsheets;

use Illuminate\Foundation\Http\FormRequest;

class CreateSpreadsheetDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'sheets' => 'required|array|min:1|max:20', // Operational limit
            'sheets.*.title' => 'required|string|max:100',
            
            'sheets.*.updates' => 'nullable|array',
            'sheets.*.updates.*.range' => 'required_with:sheets.*.updates|string',
            'sheets.*.updates.*.values' => 'required_with:sheets.*.updates|array',
            'sheets.*.updates.*.values.*' => 'array',
            
            'sheets.*.formatting' => 'nullable|array',
            'sheets.*.formatting.*.type' => 'required_with:sheets.*.formatting|string',
            'sheets.*.formatting.*.range' => 'required_if:sheets.*.formatting.*.type,format_range,number_format|string',
            'sheets.*.formatting.*.format' => 'nullable', // Array for styles, string for number_format
            'sheets.*.formatting.*.rows' => 'nullable|integer',
            'sheets.*.formatting.*.columns' => 'nullable|integer',
        ];
    }
}
