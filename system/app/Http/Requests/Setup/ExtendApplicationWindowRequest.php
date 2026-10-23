<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ExtendApplicationWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'window_id' => ['required', 'integer', 'min:1'],
            'days' => ['required', 'integer', 'min:1'],
            'baseline' => [
                'required',
                'array:cycle_id,cycle_is_active,cycle_opens_on,cycle_closes_on,school_year,semester,window_id,opens_at,closes_at,status',
            ],
            'baseline.cycle_id' => ['required', 'integer', 'min:1'],
            'baseline.cycle_is_active' => ['required', 'boolean'],
            'baseline.cycle_opens_on' => ['required', 'date_format:Y-m-d'],
            'baseline.cycle_closes_on' => ['required', 'date_format:Y-m-d'],
            'baseline.school_year' => ['required', 'string', 'max:20'],
            'baseline.semester' => ['required', 'string', 'max:8'],
            'baseline.window_id' => ['required', 'integer', 'min:1'],
            'baseline.opens_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'baseline.closes_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'baseline.status' => ['required', 'string', 'max:32'],
            'idempotency_key' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
