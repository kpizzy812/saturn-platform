<?php

namespace App\Http\Requests\Api\PermissionSet;

use App\Models\PermissionSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teamId = getTeamIdFromToken();

        $rules = [
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];

        // System permission sets can only have description, color, and icon updated
        $permissionSet = PermissionSet::forTeam($teamId)->find($this->route('id'));

        if ($permissionSet && ! $permissionSet->is_system) {
            $rules['name'] = ['string', 'max:100'];
            $rules['parent_id'] = ['nullable', 'integer', Rule::exists('permission_sets', 'id')->where('scope_type', 'team')->where('scope_id', $teamId)];
        }

        return $rules;
    }
}
