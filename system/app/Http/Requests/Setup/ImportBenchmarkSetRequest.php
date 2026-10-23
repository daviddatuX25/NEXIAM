<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

class ImportBenchmarkSetRequest extends FormRequest
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
        return [
            'benchmark_file' => ['nullable', 'file', 'mimes:json,txt', 'max:512'],
            'payload' => ['nullable', 'array:raw_ranges,synthesized_exemplars,programs,name,change_reason'],
            'payload.raw_ranges' => ['nullable', 'array'],
            'payload.synthesized_exemplars' => ['nullable', 'array'],
            'payload.programs' => ['sometimes', 'array'],
            'payload.programs.*' => ['array:code'],
            'payload.programs.*.code' => ['required_with:payload.programs.*', 'string', 'max:64'],
            'payload.synthesized_exemplars.*.program_code' => ['required_with:payload.synthesized_exemplars.*', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'change_reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', '_method', 'benchmark_file', 'payload', 'name', 'change_reason', 'idempotency_key'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                $validator->errors()->add($unknown, 'Unexpected request field.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $payload = $this->benchmarkPayload();
            } catch (JsonException) {
                $validator->errors()->add('benchmark_file', 'The benchmark file must contain valid JSON.');

                return;
            }

            $rawRanges = $payload['raw_ranges'] ?? null;
            if (! is_array($rawRanges) && ! is_array($payload['synthesized_exemplars'] ?? null)) {
                $validator->errors()->add('payload', 'Provide raw_ranges or synthesized_exemplars in the benchmark payload.');

                return;
            }

            if (is_array($rawRanges)) {
                foreach (array_keys($rawRanges) as $programCode) {
                    if (! is_string($programCode) || trim($programCode) === '') {
                        $validator->errors()->add('payload.raw_ranges', 'Raw range program codes must be non-empty strings.');

                        break;
                    }
                }
            }
            foreach ((is_array($payload['programs'] ?? null) ? $payload['programs'] : []) as $program) {
                if (! is_array($program) || ! is_string($program['code'] ?? null) || trim($program['code']) === '') {
                    $validator->errors()->add('payload.programs', 'Program codes must be non-empty strings.');

                    break;
                }
            }

            foreach ((is_array($payload['synthesized_exemplars'] ?? null) ? $payload['synthesized_exemplars'] : []) as $exemplar) {
                if (! is_array($exemplar) || ! is_string($exemplar['program_code'] ?? null) || trim($exemplar['program_code']) === '') {
                    $validator->errors()->add('payload.synthesized_exemplars', 'Exemplar program codes must be non-empty strings.');

                    break;
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function benchmarkPayload(): array
    {
        if ($this->hasFile('benchmark_file')) {
            $contents = file_get_contents($this->file('benchmark_file')->getRealPath());
            $payload = json_decode($contents === false ? '' : $contents, true, 512, JSON_THROW_ON_ERROR);
            $payload = is_array($payload) ? $payload : [];
        } else {
            $payload = $this->input('payload', []);
            $payload = is_array($payload) ? $payload : [];
        }

        if ($this->filled('name')) {
            $payload['name'] = (string) $this->input('name');
        }
        if ($this->filled('change_reason')) {
            $payload['change_reason'] = (string) $this->input('change_reason');
        }

        return $payload;
    }
}
