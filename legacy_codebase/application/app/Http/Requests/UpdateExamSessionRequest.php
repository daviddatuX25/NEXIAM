<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamSession;
use App\Rules\NoRoomConflict;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        /** @var ExamSession $session */
        $session = $this->route('exam_session');

        if (! $this->has('room_id') && $session->room_id) {
            $this->merge(['room_id' => $session->room_id]);
        }
    }

    public function rules(): array
    {
        /** @var ExamSession $session */
        $session = $this->route('exam_session');

        return [
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'room_id' => [
                'sometimes',
                'required_unless:type,direct',
                'nullable',
                'integer',
                'exists:rooms,id',
                new NoRoomConflict(
                    excludeSessionId: $session->id,
                    existingSession: $session,
                ),
            ],
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'string', 'date_format:H:i'],
            'end_time' => ['nullable', 'string', 'date_format:H:i'],
            'type' => ['sometimes', 'in:scheduled,direct'],
            'proctor_ids' => ['sometimes', 'array'],
            'proctor_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
