<?php

namespace App\Http\Requests\Api\Alert;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'metric' => 'required|string|in:cpu,memory,disk,error_rate,response_time',
            'condition' => 'required|string|in:>,<,=',
            'threshold' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1|max:1440',
            'enabled' => 'sometimes|boolean',
            'channels' => 'nullable|array',
            'channels.*' => 'string|in:email,slack,discord,telegram,pagerduty,webhook',
        ];
    }
}
