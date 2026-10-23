<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CaseConversationApplicantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->only(['program_code', 'cycle_code', 'search']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'program_code' => ['required', 'string', 'max:40'],
            'cycle_code' => ['required', 'string', 'max:64'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->query()), ['program_code', 'cycle_code', 'search']) !== []) {
                $validator->errors()->add('request', 'Unsupported request fields.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'validation_failed',
            'message' => 'The applicant lookup request is invalid.',
            'errors' => $validator->errors(),
        ], 422, ['Cache-Control' => 'private, no-store']));
    }
}
