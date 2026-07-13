<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.started_at' => ['required', 'date'],
            'sessions.*.ended_at' => ['required', 'date'],
            'sessions.*.breaks' => ['nullable', 'array'],
            'sessions.*.breaks.*.started_at' => ['required_with:sessions.*.breaks', 'date'],
            'sessions.*.breaks.*.ended_at' => ['required_with:sessions.*.breaks', 'date'],
        ];
    }
}
