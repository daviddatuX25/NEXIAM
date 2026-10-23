<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\Foundation\SpaceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRoomRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('rooms', 'code')->where(fn ($q) => $q->where('institution_id', Institution::current()->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'building_name' => ['nullable', 'string', 'max:100'],
            'floor_level' => ['nullable', 'string', 'max:32'],
            'directions_hint' => ['nullable', 'string', 'max:2000'],
            'space_kind' => ['sometimes', new Enum(SpaceKind::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
