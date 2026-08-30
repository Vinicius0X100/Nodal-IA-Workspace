<?php

namespace App\Http\Requests\Artifacts\Spreadsheets;

use Illuminate\Foundation\Http\FormRequest;

class SpreadsheetDraftViewportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // We use org.access middleware or controller validation
    }

    public function rules(): array
    {
        return [
            'sheet' => 'required|string',
            'range' => 'required|string|max:50',
        ];
    }
}
