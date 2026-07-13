<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacationBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_entitlement_days' => ['required', 'numeric', 'min:0', 'max:366'],
            'carryover_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'manual_adjustment_days' => ['nullable', 'numeric', 'min:-366', 'max:366'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
