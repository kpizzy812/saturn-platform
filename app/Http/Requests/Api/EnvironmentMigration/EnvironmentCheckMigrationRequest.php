<?php

namespace App\Http\Requests\Api\EnvironmentMigration;

use Illuminate\Foundation\Http\FormRequest;

class EnvironmentCheckMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_environment_uuid' => 'required|string',
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'required|integer|exists:servers,id',
            'resources' => 'required|array|min:1',
            'resources.*.type' => 'required|string|in:application,service,database',
            'resources.*.uuid' => 'required|string',
        ];
    }
}
