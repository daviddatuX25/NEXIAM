<?php

namespace App\Http\Requests\Setup;

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SandboxPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $aptitudeCodes = AptitudeArea::query()
            ->where('is_active', true)
            ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())
            ->orderBy('id')
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        $inputKeys = ['gwa', 'aptitudes'];
        $rules = [
            'source' => ['required', Rule::in(['active', 'draft'])],
            'revision_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'input' => ['required', 'array:'.implode(',', $inputKeys)],
            'input.gwa' => ['required', 'numeric', 'min:1', 'max:100'],
            'input.aptitudes' => ['required', 'array:'.implode(',', $aptitudeCodes)],
        ];

        foreach ($aptitudeCodes as $code) {
            $rules["input.aptitudes.{$code}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', 'source', 'revision_id', 'request_id', 'input'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                $validator->errors()->add($unknown, 'Unexpected request field.');
            }

            $input = $this->input('input');
            if (is_array($input)) {
                $allowedInput = ['gwa', 'aptitudes'];
                foreach (array_diff(array_keys($input), $allowedInput) as $unknown) {
                    $validator->errors()->add("input.{$unknown}", 'Unexpected preview input field.');
                }
            }

            $aptitudes = is_array($input) ? ($input['aptitudes'] ?? null) : null;
            if (! is_array($aptitudes)) {
                return;
            }

            $catalogCodes = AptitudeArea::query()
                ->where('is_active', true)
                ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->all();
            foreach (array_diff(array_keys($aptitudes), $catalogCodes) as $unknown) {
                $validator->errors()->add("input.aptitudes.{$unknown}", 'Unknown aptitude catalog key.');
            }
        }];
    }
}
