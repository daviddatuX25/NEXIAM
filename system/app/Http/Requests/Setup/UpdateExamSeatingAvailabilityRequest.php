<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateExamSeatingAvailabilityRequest extends FormRequest
{
    public function authorize(CapabilityAuthorizer $authorizer): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }

        $institution = Institution::current();

        return $institution !== null
            && $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $institution->id,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enable_seating_arrangements' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = ['enable_seating_arrangements', 'expected_version', '_token', '_method'];

                if (array_diff(array_keys($this->all()), $allowed) !== []) {
                    $validator->errors()->add('policy', 'Unsupported exam seating policy fields were supplied.');
                }
            },
        ];
    }
}
