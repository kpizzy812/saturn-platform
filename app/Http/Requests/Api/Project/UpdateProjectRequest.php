<?php

namespace App\Http\Requests\Api\Project;

use App\Support\ValidationPatterns;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ValidationPatterns::nameRules(required: false),
            'description' => ValidationPatterns::descriptionRules(),
        ];
    }

    public function messages(): array
    {
        return ValidationPatterns::combinedMessages();
    }
}
