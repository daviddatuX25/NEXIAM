<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class RecordScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The attempt is bound from the route (`{attempt}`); the action guards
     * roster membership, so no body field is needed here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.aptitude_area_id' => ['required', 'integer', 'distinct', 'exists:aptitude_areas,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
