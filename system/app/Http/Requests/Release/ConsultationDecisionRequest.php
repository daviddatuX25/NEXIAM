<?php

namespace App\Http\Requests\Release;

use App\Models\Foundation\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConsultationDecisionRequest extends FormRequest
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
        $hasCombinedEndorsement = array_key_exists('default_program_code', $this->all())
            || array_key_exists('pool_options', $this->all())
            || array_key_exists('pool_reason', $this->all());
        $institutionId = Institution::current()->id;

        $programRule = Rule::exists('programs', 'code')->where(
            fn ($query) => $query->where('institution_id', $institutionId)->where('is_active', true),
        );

        return [
            'decision_type' => ['required', 'string', 'in:approve,modify,override,defer,refer'],
            'target_program_code' => [
                'nullable',
                'required_if:decision_type,refer',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(
                    fn ($query) => $query->where('institution_id', $institutionId)->where('is_active', true),
                ),
            ],
            'referral_reason' => ['nullable', 'required_if:decision_type,refer', 'string', 'max:500'],
            'notes' => ['nullable', 'required_if:decision_type,override,defer', 'string', 'max:5000'],
            'default_program_code' => [
                $hasCombinedEndorsement ? 'required' : 'nullable',
                'string',
                'max:32',
                $programRule,
                'prohibited_unless:decision_type,approve,modify,override',
            ],
            'pool_options' => [
                $hasCombinedEndorsement ? 'required' : 'nullable',
                'array',
                'min:1',
                'prohibited_unless:decision_type,approve,modify,override',
            ],
            'pool_options.*.program_code' => ['required', 'string', 'max:32', 'distinct', $programRule],
            'pool_options.*.display_order' => ['required', 'integer', 'min:1', 'distinct'],
            'pool_options.*.basis' => ['required', 'in:preference,guidance_option'],
            'pool_reason' => ['nullable', 'string', 'max:500', 'prohibited_unless:decision_type,approve,modify,override'],
            'encounter_id' => ['nullable', 'integer', Rule::exists('consultation_encounters', 'id')->where('admission_attempt_id', $this->route('attempt')?->id)],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $defaultProgramCode = $this->input('default_program_code');
                $poolOptions = $this->input('pool_options');

                if (! is_string($defaultProgramCode) || ! is_array($poolOptions)) {
                    return;
                }

                foreach ($poolOptions as $option) {
                    if (is_array($option) && ($option['program_code'] ?? null) === $defaultProgramCode) {
                        return;
                    }
                }

                $validator->errors()->add(
                    'default_program_code',
                    'The default program must be included in the program pool.',
                );
            },
        ];
    }
}
