<?php

namespace App\Http\Requests\Api\GitAnalyzer;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeGitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'git_repository' => ['required', 'string', 'regex:/^(https?:\/\/|git@)/'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'private_key_id' => ['nullable', 'integer', 'exists:private_keys,id'],
            'source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'in:github,gitlab,bitbucket'],
            'github_app_id' => ['nullable', 'integer'],
        ];
    }
}
