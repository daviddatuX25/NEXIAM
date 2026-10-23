<?php

namespace App\Http\Requests\Admission\OfficeInquiries;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CreateApplicantOfficeInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'office' => ['required', 'string', 'in:registrar,guidance'],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== []) {
                $validator->errors()->add('request', 'Unsupported request fields.');
            }
            foreach (['body' => 16384, 'subject' => 480] as $field => $bytes) {
                $value = $this->input($field);
                if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || strlen($value) > $bytes || trim($value) === '')) {
                    $validator->errors()->add($field, 'The text is invalid or too long.');
                }
            }
            foreach (['observed_sequence', 'after_sequence', 'before_sequence', 'before_id'] as $field) {
                $value = $this->input($field);
                if (is_string($value) && (strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0))) {
                    $validator->errors()->add($field, 'The cursor is outside the supported range.');
                }
            }
            if ($this->filled('after_sequence') && $this->filled('before_sequence')) {
                $validator->errors()->add('request', 'Choose only one cursor.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'validation_failed',
            'message' => 'The inquiry request is invalid.',
        ], 422, ['Cache-Control' => 'private, no-store']));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'session_expired',
            'message' => 'Sign in again to continue.',
        ], 401, ['Cache-Control' => 'private, no-store']));
    }
}
