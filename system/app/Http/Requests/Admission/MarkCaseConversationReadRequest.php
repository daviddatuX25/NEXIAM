<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class MarkCaseConversationReadRequest extends FormRequest
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
        return $this->only(['observed_sequence', 'observation_token']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_sequence' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,18})$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0))) {
                    $fail('The observed sequence is outside the supported range.');
                }
            }],
            'observation_token' => ['required', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->except(['_token', '_method'])), ['observed_sequence', 'observation_token']) !== []) {
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
