<?php

namespace App\Http\Requests\Api\PermissionSet;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionSetUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'environment_overrides' => ['nullable', 'array'],
        ];
    }
}
