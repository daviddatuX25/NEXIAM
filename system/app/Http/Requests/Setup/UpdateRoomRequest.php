<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateRoomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'building_name' => ['nullable', 'string', 'max:100'],
            'floor_level' => ['nullable', 'string', 'max:32'],
            'directions_hint' => ['nullable', 'string', 'max:2000'],
            'space_kind' => ['sometimes', new Enum(SpaceKind::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $room = $this->route('room');
                if (! $room instanceof Room || ! is_numeric($this->input('capacity'))) {
                    return;
                }
                $spaceKindInput = $this->input('space_kind');
                $targetKind = $spaceKindInput !== null
                    ? SpaceKind::tryFrom((string) $spaceKindInput)
                    : $room->space_kind;

                if ($targetKind !== null && ! $targetKind->allowsSeatingArrangement()) {
                    return;
                }

                $capacity = (int) $this->input('capacity');
                $defaultArrangement = $room->defaultSeatingArrangement;
                if ($defaultArrangement !== null && $defaultArrangement->desk_count > $capacity) {
                    $validator->errors()->add(
                        'capacity',
                        "Capacity must be at least {$defaultArrangement->desk_count} for default seating arrangement {$defaultArrangement->name}.",
                    );
                }
            },
        ];
    }
}
