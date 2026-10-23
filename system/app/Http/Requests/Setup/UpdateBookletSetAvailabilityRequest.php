<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateBookletSetAvailabilityRequest extends FormRequest
{
    public function authorize(CapabilityAuthorizer $authorizer): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }

        $institution = Institution::current();

        return $institution !== null
            && $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, [
                'institution_id' => (int) $institution->id,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enable_multi_set_booklets' => ['required', 'boolean'],
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
                $allowed = ['enable_multi_set_booklets', 'expected_version', '_token', '_method'];

                if (array_diff(array_keys($this->all()), $allowed) !== []) {
                    $validator->errors()->add('policy', 'Unsupported Booklet Set policy fields were supplied.');
                }
            },
        ];
    }
}
