<?php

namespace App\Http\Requests\Api\GitAnalyzer;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionGitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'environment_uuid' => ['required', 'string', 'exists:environments,uuid'],
            'destination_uuid' => ['required', 'string'],
            'git_repository' => ['required', 'string', 'regex:/^(https?:\/\/|git@)/'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'private_key_id' => ['nullable', 'integer', 'exists:private_keys,id'],
            'source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'in:github,gitlab,bitbucket'],
            'github_app_id' => ['nullable', 'integer'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.name' => ['required', 'string'],
            'applications.*.enabled' => ['required', 'boolean'],
            'applications.*.base_directory' => ['nullable', 'string', 'max:255'],
            'applications.*.application_type' => ['nullable', 'string', 'in:web,worker,both'],
            'applications.*.env_vars' => ['nullable', 'array'],
            'applications.*.env_vars.*.key' => ['required', 'string', 'max:255'],
            'applications.*.env_vars.*.value' => ['required', 'string', 'max:10000'],
            'databases' => ['nullable', 'array'],
            'databases.*.type' => ['required', 'string', 'in:postgresql,mysql,mongodb,redis,clickhouse'],
            'databases.*.enabled' => ['required', 'boolean'],
        ];
    }
}
