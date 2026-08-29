<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpreadsheetValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'updates' => 'required|array|min:1|max:50',
            'updates.*.range' => 'required|string|max:255',
            'updates.*.values' => 'required|array|min:1',
            'updates.*.values.*' => 'required|array',
            'updates.*.values.*.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!is_string($value) && !is_numeric($value) && !is_bool($value) && !is_null($value)) {
                        $fail("O valor da célula deve ser numérico, booleano ou texto (string). Objetos ou arrays não são suportados.");
                    }
                }
            ],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $updates = $this->input('updates', []);
            if (!is_array($updates)) {
                return;
            }

            $totalCells = 0;
            foreach ($updates as $update) {
                if (!isset($update['values']) || !is_array($update['values'])) {
                    continue;
                }

                foreach ($update['values'] as $row) {
                    if (is_array($row)) {
                        $totalCells += count($row);
                    }
                }
            }

            if ($totalCells > 10000) {
                $validator->errors()->add('updates', 'O limite total de células por requisição é 10.000. Você enviou ' . $totalCells . ' células.');
            }
        });
    }
}
