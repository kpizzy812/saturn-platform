<?php

namespace App\Http\Requests\Api\PermissionSet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teamId = getTeamIdFromToken();

        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', Rule::exists('permission_sets', 'id')->where('scope_type', 'team')->where('scope_id', $teamId)],
            'permissions' => ['array'],
            'permissions.*.permission_id' => ['required_with:permissions', 'integer', 'exists:permissions,id'],
            'permissions.*.environment_restrictions' => ['nullable', 'array'],
        ];
    }
}
