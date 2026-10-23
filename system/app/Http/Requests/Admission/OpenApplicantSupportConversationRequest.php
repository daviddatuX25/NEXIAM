<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class OpenApplicantSupportConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->only(['subject', 'body', 'idempotency_key']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $text = static function (int $maximum): \Closure {
            return static function (string $attribute, mixed $value, \Closure $fail) use ($maximum): void {
                if (! is_string($value)
                    || ! mb_check_encoding(trim($value), 'UTF-8')
                    || trim($value) === ''
                    || mb_strlen(trim($value), 'UTF-8') > $maximum
                    || strlen(trim($value)) > 16384) {
                    $fail('The text is blank, invalid UTF-8, or exceeds the supported limit.');
                }
            };
        };

        return [
            'subject' => ['required', 'string', $text(120)],
            'body' => ['required', 'string', $text(4000)],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function prepareForValidation(): void
    {
        $values = [];
        foreach (['subject', 'body'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        $this->merge($values);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->except(['_token', '_method'])), ['subject', 'body', 'idempotency_key']) !== []) {
                $validator->errors()->add('request', 'Unsupported request fields.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'code' => 'validation_failed',
                'message' => 'The conversation request is invalid.',
                'errors' => $validator->errors(),
            ], 422, ['Cache-Control' => 'private, no-store']));
        }

        $response = back()->withErrors($validator);
        $response->headers->set('Cache-Control', 'private, no-store');

        throw new HttpResponseException($response);
    }
}
