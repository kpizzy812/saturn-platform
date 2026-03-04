<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreviewSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fqdn' => ['nullable', 'string', 'max:255'],
            'custom_labels' => ['nullable', 'string'],
            'docker_compose_domains' => ['nullable', 'string'],
        ];
    }
}
