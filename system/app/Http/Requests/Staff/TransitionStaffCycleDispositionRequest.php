<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionStaffCycleDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'destination_cycle_code' => ['required', 'string', 'max:64'],
            'dispositions' => ['required', 'array', 'min:1', 'max:200'],
            'dispositions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'dispositions.*.disposition' => ['required', 'string', 'in:retain,rotate,archive'],
            'dispositions.*.program_codes' => ['nullable', 'array'],
            'dispositions.*.program_codes.*' => ['string'],
            'dispositions.*.department_id' => ['nullable', 'integer'],
        ];
    }
}
