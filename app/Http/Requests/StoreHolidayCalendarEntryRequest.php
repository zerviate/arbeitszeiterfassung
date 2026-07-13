<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayCalendarEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
