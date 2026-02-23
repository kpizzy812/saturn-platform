<?php

namespace App\Http\Requests\Api\EnvironmentMigration;

use Illuminate\Foundation\Http\FormRequest;

class StoreMigrationRequest extends FormRequest
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
            'target_server_id' => 'required|integer|exists:servers,id',
            'options' => 'nullable|array',
            'options.mode' => 'nullable|string|in:clone,promote',
            'options.copy_env_vars' => 'nullable|boolean',
            'options.copy_volumes' => 'nullable|boolean',
            'options.update_existing' => 'nullable|boolean',
            'options.config_only' => 'nullable|boolean',
            'options.auto_deploy' => 'nullable|boolean',
            'options.fqdn' => 'nullable|string|max:255',
            'dry_run' => 'nullable|boolean',
        ];
    }
}
