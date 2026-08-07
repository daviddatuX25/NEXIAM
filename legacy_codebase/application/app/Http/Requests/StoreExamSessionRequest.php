<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\NoRoomConflict;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator']) ?? false;
    }

    public function rules(): array
    {
        $backtrack = filter_var($this->input('backtrack'), FILTER_VALIDATE_BOOLEAN);

        $dateRules = ['required', 'date'];
        if (! $backtrack) {
            $dateRules[] = 'after_or_equal:today';
        }

        return [
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'room_id' => ['required_unless:type,direct', 'nullable', 'integer', 'exists:rooms,id', new NoRoomConflict],
            'date' => $dateRules,
            'start_time' => ['required', 'string', 'date_format:H:i'],
            'end_time' => ['nullable', 'string', 'date_format:H:i'],
            'type' => ['sometimes', 'in:scheduled,direct'],
            'proctor_ids' => ['sometimes', 'array'],
            'proctor_ids.*' => ['integer', 'exists:users,id'],
            'backtrack' => ['nullable', 'boolean'],
        ];
    }
}
