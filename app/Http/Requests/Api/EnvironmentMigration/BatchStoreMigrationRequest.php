<?php

namespace App\Http\Requests\Api\EnvironmentMigration;

use Illuminate\Foundation\Http\FormRequest;

class BatchStoreMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'required|integer|exists:servers,id',
            'resources' => 'required|array|min:1|max:50',
            'resources.*.type' => 'required|string|in:application,service,database',
            'resources.*.uuid' => 'required|string',
            'options' => 'nullable|array',
        ];
    }
}
