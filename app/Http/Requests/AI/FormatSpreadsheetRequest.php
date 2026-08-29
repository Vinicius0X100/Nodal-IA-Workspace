<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormatSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticado via middleware
    }

    public function rules(): array
    {
        return [
            'operations' => 'required|array|min:1|max:50',
            
            'operations.*.type' => [
                'required',
                'string',
                Rule::in([
                    'format_range',
                    'number_format',
                    'borders',
                    'freeze',
                    'auto_resize_columns',
                    'set_column_width',
                    'set_row_height',
                    'merge_cells'
                ])
            ],
            
            'operations.*.sheet' => 'nullable|string|max:255',
            
            // Validate specific fields per operation
            // Ranges (all except freeze need a range)
            'operations.*.range' => 'exclude_if:operations.*.type,freeze|required|string|max:255',
            
            // Format Range
            'operations.*.format' => 'required_if:operations.*.type,format_range,number_format',
            'operations.*.format.background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'operations.*.format.text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'operations.*.format.bold' => 'nullable|boolean',
            'operations.*.format.italic' => 'nullable|boolean',
            'operations.*.format.font_size' => 'nullable|integer|min:6|max:72',
            'operations.*.format.horizontal_alignment' => [
                'nullable',
                Rule::in(['LEFT', 'CENTER', 'RIGHT'])
            ],
            'operations.*.format.vertical_alignment' => [
                'nullable',
                Rule::in(['TOP', 'MIDDLE', 'BOTTOM'])
            ],
            'operations.*.format.wrap' => 'nullable|boolean',

            // Number Format
            // Se o tipo for number_format, o campo 'format' é uma string ao invés de array
            // Para lidar com isso de forma limpa, não colocarei array strict no format acima.
            
            // Borders
            'operations.*.style' => [
                'required_if:operations.*.type,borders',
                'nullable',
                Rule::in(['SUBTLE', 'SOLID', 'THICK', 'NONE'])
            ],

            // Freeze
            'operations.*.rows' => 'nullable|integer|min:0|max:1000',
            'operations.*.columns' => 'nullable|integer|min:0|max:100',

            // Set Column Width / Row Height
            'operations.*.width_px' => 'required_if:operations.*.type,set_column_width|integer|min:40|max:600',
            'operations.*.height_px' => 'required_if:operations.*.type,set_row_height|integer|min:20|max:400',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $operations = $this->input('operations', []);
            if (!is_array($operations)) {
                return;
            }

            foreach ($operations as $index => $op) {
                if (!isset($op['type'])) continue;
                
                $type = $op['type'];

                if ($type === 'format_range') {
                    if (!isset($op['format']) || !is_array($op['format'])) {
                        $validator->errors()->add("operations.{$index}.format", "O campo format deve ser um objeto/array para format_range.");
                    }
                }

                if ($type === 'number_format') {
                    if (!isset($op['format']) || !is_string($op['format'])) {
                        $validator->errors()->add("operations.{$index}.format", "O campo format deve ser uma string com o preset para number_format.");
                    } else {
                        $validPresets = [
                            'CURRENCY_BRL', 'CURRENCY_USD', 'INTEGER', 'DECIMAL_2',
                            'PERCENT', 'DATE_DMY', 'DATE_YMD', 'DATETIME_DMY'
                        ];
                        if (!in_array($op['format'], $validPresets)) {
                            $validator->errors()->add("operations.{$index}.format", "Preset de number_format não suportado.");
                        }
                    }
                }

                if ($type === 'freeze') {
                    if (!isset($op['rows']) && !isset($op['columns'])) {
                        $validator->errors()->add("operations.{$index}", "Operação freeze requer 'rows' ou 'columns'.");
                    }
                }
                if (isset($op['range'])) {
                    try {
                        $parsed = \App\Domain\AI\Utils\A1Parser::parse($op['range']);
                        $rangeSheet = $parsed['sheetTitle'];
                        $opSheet = $op['sheet'] ?? null;
                        
                        if ($rangeSheet !== null && $opSheet !== null && $rangeSheet !== $opSheet) {
                            $validator->errors()->add("operations.{$index}.sheet", "Ambiguidade: o título da aba no campo 'sheet' ({$opSheet}) é diferente do título no campo 'range' ({$rangeSheet}).");
                        }

                        $requiresBounded = in_array($type, ['format_range', 'number_format', 'borders', 'merge_cells']);
                        if ($requiresBounded && !$parsed['isBounded']) {
                            $validator->errors()->add("operations.{$index}.range", "A operação {$type} exige um range com limites definidos de linha e coluna (ex: A1:D10). Ranges abertos não são permitidos nesta operação.");
                        }

                        if ($type === 'merge_cells' && $parsed['isBounded']) {
                            $grid = $parsed['gridRange'];
                            $cellsCount = ($grid['endColumnIndex'] - $grid['startColumnIndex']) * ($grid['endRowIndex'] - $grid['startRowIndex']);
                            if ($cellsCount < 2) {
                                $validator->errors()->add("operations.{$index}.range", "A operação merge_cells exige um range com pelo menos 2 células.");
                            }
                        }
                    } catch (\InvalidArgumentException $e) {
                        $validator->errors()->add("operations.{$index}.range", $e->getMessage());
                    }
                }
            }
        });
    }
}
