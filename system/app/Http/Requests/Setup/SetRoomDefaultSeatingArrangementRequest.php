<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class SetRoomDefaultSeatingArrangementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('seating_arrangement_id') && $this->string('seating_arrangement_id')->trim()->isEmpty()) {
            $this->merge(['seating_arrangement_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seating_arrangement_id' => ['nullable', 'integer', 'exists:seating_arrangements,id'],
        ];
    }
}
