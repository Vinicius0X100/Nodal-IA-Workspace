<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class UploadResourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * A autorização real (capabilities) é feita no Controller/Service via AuthorizationService.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Limite em KB para a regra do Laravel (config retorna MB)
        $maxSizeKilobytes = (int) config('nodal.max_upload_size_mb', 50) * 1024;

        return [
            'file'                 => "required|file|max:{$maxSizeKilobytes}",
            'parent_resource_uuid' => 'nullable|uuid',
        ];
    }

    /**
     * Mensagens de erro personalizadas.
     */
    public function messages(): array
    {
        $maxMb = config('nodal.max_upload_size_mb', 50);

        return [
            'file.required' => 'O arquivo é obrigatório.',
            'file.file'     => 'O campo enviado deve ser um arquivo válido.',
            'file.max'      => "O arquivo não pode ser maior que {$maxMb} MB.",
            'parent_resource_uuid.uuid' => 'O identificador da pasta de destino deve ser um UUID válido.',
        ];
    }
}
