<?php

namespace App\Http\Requests\Api\PermissionSet;

use Illuminate\Foundation\Http\FormRequest;

class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'permissions.*.environment_restrictions' => ['nullable', 'array'],
        ];
    }
}
