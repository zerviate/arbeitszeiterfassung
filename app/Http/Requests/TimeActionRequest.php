<?php

namespace App\Http\Requests;

use App\Models\TimeEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['nullable', 'date'],
            'source' => [
                'nullable',
                Rule::in([
                    TimeEvent::SOURCE_WEB,
                    TimeEvent::SOURCE_MOBILE,
                    TimeEvent::SOURCE_TERMINAL,
                    TimeEvent::SOURCE_ADMIN,
                    TimeEvent::SOURCE_IMPORT,
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
