<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ReadCaseConversationRequest extends FormRequest
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
        return $this->only(['after_sequence', 'before_sequence']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'after_sequence' => ['nullable', 'string', 'regex:/^(0|[1-9][0-9]{0,18})$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0))) {
                    $fail('The cursor is outside the supported range.');
                }
            }],
            'before_sequence' => ['nullable', 'string', 'regex:/^(0|[1-9][0-9]{0,18})$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0))) {
                    $fail('The cursor is outside the supported range.');
                }
            }],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('after_sequence') && $this->filled('before_sequence')) {
                $validator->errors()->add('after_sequence', 'Choose only one conversation cursor.');
            }
            if (array_diff(array_keys($this->except(['_token', '_method'])), ['after_sequence', 'before_sequence']) !== []) {
                $validator->errors()->add('request', 'Unsupported request fields.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'validation_failed',
            'message' => 'The conversation request is invalid.',
            'errors' => $validator->errors(),
        ], 422, ['Cache-Control' => 'private, no-store']));
    }
}
