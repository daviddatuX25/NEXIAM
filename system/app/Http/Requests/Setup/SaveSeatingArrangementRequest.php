<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\Foundation\SeatingArrangement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveSeatingArrangementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('room_ids')) {
            $this->merge(['room_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'rows' => ['required', 'integer', 'min:1', 'max:500'],
            'columns' => ['required', 'integer', 'min:1', 'max:500'],
            'booklet_pattern' => ['required', Rule::in(SeatingArrangement::PATTERNS)],
            'availability_mode' => ['required', Rule::in(SeatingArrangement::AVAILABILITY_MODES)],
            'room_ids' => ['present', 'array'],
            'room_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('rooms', 'id')->where('institution_id', Institution::current()->id),
            ],
            'is_active' => ['required', 'boolean'],
            'lock_version' => [
                $this->route('arrangement') === null ? 'nullable' : 'required',
                'integer',
                'min:1',
            ],
        ];
    }
}
