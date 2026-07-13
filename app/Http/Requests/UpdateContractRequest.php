<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'weekly_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'workdays_pattern' => ['required', 'array'],
            'workdays_pattern.monday' => ['required', 'boolean'],
            'workdays_pattern.tuesday' => ['required', 'boolean'],
            'workdays_pattern.wednesday' => ['required', 'boolean'],
            'workdays_pattern.thursday' => ['required', 'boolean'],
            'workdays_pattern.friday' => ['required', 'boolean'],
            'workdays_pattern.saturday' => ['required', 'boolean'],
            'workdays_pattern.sunday' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
