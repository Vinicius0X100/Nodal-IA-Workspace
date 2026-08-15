<?php

namespace App\Http\Requests\AI;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReadMultipleResourcesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resource_uuids' => ['required', 'array', 'min:1', 'max:10'],
            'resource_uuids.*' => ['required', 'uuid'],
        ];
    }
    
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('resource_uuids') && is_array($this->resource_uuids)) {
            // Remove duplicatas mantendo apenas valores únicos indexados sequencialmente
            $this->merge([
                'resource_uuids' => array_values(array_unique($this->resource_uuids))
            ]);
        }
    }
}
