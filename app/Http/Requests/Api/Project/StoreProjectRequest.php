<?php

namespace App\Http\Requests\Api\Project;

use App\Support\ValidationPatterns;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ValidationPatterns::nameRules(),
            'description' => ValidationPatterns::descriptionRules(),
        ];
    }

    public function messages(): array
    {
        return ValidationPatterns::combinedMessages();
    }
}
