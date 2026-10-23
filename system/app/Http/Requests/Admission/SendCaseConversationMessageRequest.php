<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class SendCaseConversationMessageRequest extends FormRequest
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
        return $this->only(['body', 'idempotency_key']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value)
                    || ! mb_check_encoding(trim($value), 'UTF-8')
                    || trim($value) === ''
                    || mb_strlen(trim($value), 'UTF-8') > 4000
                    || strlen(trim($value)) > 16384) {
                    $fail('The message must be valid UTF-8, 1–4000 characters, and at most 16 KiB.');
                }
            }],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->except(['_token', '_method'])), ['body', 'idempotency_key']) !== []) {
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
