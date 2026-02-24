<?php

namespace App\Http\Requests\Api\EnvironmentMigration;

use Illuminate\Foundation\Http\FormRequest;

class CheckMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => 'required|string|in:application,service,database',
            'source_uuid' => 'required|string',
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'nullable|integer|exists:servers,id',
            'options' => 'nullable|array',
        ];
    }
}
