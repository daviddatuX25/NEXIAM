<?php

namespace App\Http\Requests\Setup;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveTestKitTestSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $instrument = $this->input('instrument');
        $metrics = is_array($instrument) ? ($instrument['metrics'] ?? null) : null;

        if (! is_array($metrics)) {
            return;
        }

        foreach ($metrics as $index => $metric) {
            if (is_array($metric) && (! array_key_exists('is_required', $metric) || $metric['is_required'] === null)) {
                $metrics[$index]['is_required'] = true;
            }
        }

        $instrument['metrics'] = $metrics;
        $this->merge(['instrument' => $instrument]);
    }

    public function rules(): array
    {
        $rules = [
            'preparation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expected_preparation_revision' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'instrument' => ['required', 'array:id,version_id,expected_revision,profile,subtests,metrics'],
            'instrument.id' => ['present', 'nullable', 'integer', 'min:1'],
            'instrument.version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'instrument.expected_revision' => ['present', 'nullable', 'integer', 'min:1'],
            'instrument.profile' => [
                'required',
                'array:code,display_name,publisher_name,form_code,edition,language,source_reference,permitted_use_reference,material_requirement,administration_procedure_reference',
            ],
            'instrument.profile.code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'instrument.profile.display_name' => ['required', 'string', 'max:255'],
            'instrument.profile.publisher_name' => ['nullable', 'string', 'max:255'],
            'instrument.profile.form_code' => ['nullable', 'string', 'max:64'],
            'instrument.profile.edition' => ['nullable', 'string', 'max:128'],
            'instrument.profile.language' => ['nullable', 'string', 'max:32'],
            'instrument.profile.source_reference' => ['nullable', 'string', 'max:500'],
            'instrument.profile.permitted_use_reference' => ['nullable', 'string', 'max:500'],
            'instrument.profile.material_requirement' => ['required', Rule::in(['unresolved', 'paper_backed', 'no_paper'])],
            'instrument.profile.administration_procedure_reference' => ['nullable', 'string', 'max:500'],
            'instrument.subtests' => ['present', 'array', 'max:40'],
            'instrument.subtests.*' => ['required', 'array:code,name,display_order,response_mode,item_order_policy,items'],
            'instrument.subtests.*.code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'instrument.subtests.*.name' => ['required', 'string', 'max:255'],
            'instrument.subtests.*.display_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'instrument.subtests.*.response_mode' => ['required', Rule::in(['keyed_items', 'recorded_measure'])],
            'instrument.subtests.*.item_order_policy' => ['required', Rule::in(['fixed', 'within_subtest_permutable'])],
            'instrument.subtests.*.items' => ['present', 'array', 'max:500'],
            'instrument.subtests.*.items.*' => ['required', 'array:item_code,local_number,display_order,is_scored,option_tokens'],
            'instrument.subtests.*.items.*.item_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'instrument.subtests.*.items.*.local_number' => ['required', 'string', 'max:32'],
            'instrument.subtests.*.items.*.display_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'instrument.subtests.*.items.*.is_scored' => ['required', 'boolean'],
            'instrument.subtests.*.items.*.option_tokens' => ['present', 'array', 'max:10'],
            'instrument.subtests.*.items.*.option_tokens.*' => ['required', 'string', 'max:32'],
            'instrument.metrics' => ['present', 'array', 'max:100'],
            'instrument.metrics.*' => ['required', 'array:code,name,rule_kind,subtest_code,unit,min_raw_value,max_raw_value,precision_scale,is_required,required_output_scales,procedure_reference,construct_aptitude_area_id,input_codes,input_contract_json,formula_expression,formula_ast_json,item_codes'],
            'instrument.metrics.*.input_contract_json' => ['sometimes', 'nullable', 'array:allowed_sources,required_resources,source_requirements'],
            'instrument.metrics.*.input_contract_json.allowed_sources' => ['required_with:instrument.metrics.*.input_contract_json', 'array'],
            'instrument.metrics.*.input_contract_json.allowed_sources.*' => ['required', 'string'],
            'instrument.metrics.*.input_contract_json.required_resources' => ['sometimes', 'array'],
            'instrument.metrics.*.input_contract_json.required_resources.*' => ['required', 'string'],
            'instrument.metrics.*.input_contract_json.source_requirements' => ['required_with:instrument.metrics.*.input_contract_json', 'array'],
            'instrument.metrics.*.input_contract_json.source_requirements.*' => ['required', 'array:unit,identity_requirements'],
            'instrument.metrics.*.input_contract_json.source_requirements.*.unit' => ['required', 'string', 'max:64'],
            'instrument.metrics.*.input_contract_json.source_requirements.*.identity_requirements' => ['required', 'array'],
            'instrument.metrics.*.input_contract_json.source_requirements.*.identity_requirements.*' => ['required', 'string'],
            'instrument.metrics.*.code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'instrument.metrics.*.name' => ['required', 'string', 'max:255'],
            'instrument.metrics.*.rule_kind' => ['required', Rule::in(['correct_item_count', 'sum_raw_metrics', 'formula_raw_metrics', 'recorded_measure', 'external_final'])],
            'instrument.metrics.*.subtest_code' => ['nullable', 'string', 'max:32'],
            'instrument.metrics.*.unit' => ['nullable', 'string', 'max:64'],
            'instrument.metrics.*.min_raw_value' => ['nullable', 'numeric'],
            'instrument.metrics.*.max_raw_value' => ['nullable', 'numeric'],
            'instrument.metrics.*.precision_scale' => ['nullable', 'integer', 'min:0', 'max:6'],
            'instrument.metrics.*.is_required' => ['required', 'boolean'],
            'instrument.metrics.*.required_output_scales' => ['present', 'array', 'max:3'],
            'instrument.metrics.*.required_output_scales.*' => ['required', Rule::in(['percentile_rank', 'stanine', 'category'])],
            'instrument.metrics.*.procedure_reference' => ['nullable', 'string', 'max:500'],
            'instrument.metrics.*.construct_aptitude_area_id' => ['nullable', 'integer', 'exists:aptitude_areas,id'],
            'instrument.metrics.*.input_codes' => ['present', 'array', 'max:100'],
            'instrument.metrics.*.formula_expression' => ['nullable', 'string', 'max:2048'],
            'instrument.metrics.*.formula_ast_json' => ['sometimes', 'nullable', 'array'],
            'instrument.metrics.*.item_codes' => ['sometimes', 'nullable', 'array', 'max:500'],
            'instrument.metrics.*.item_codes.*' => ['required', 'string', 'max:64'],
            'instrument.metrics.*.input_codes.*' => ['required', 'string', 'max:32'],
            'norm' => ['sometimes', 'nullable', 'array:package_id,version_id,expected_revision,package,tables'],
            'norm.package_id' => ['present', 'nullable', 'integer', 'min:1'],
            'norm.version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'norm.expected_revision' => ['present', 'nullable', 'integer', 'min:1'],
            'norm.package' => ['sometimes', 'nullable', 'array:code,display_name,publisher_name,form_code,edition,language,applicable_population,source_reference,permitted_use_reference'],
            'norm.package.code' => ['required_with:norm.package', 'nullable', 'string', 'max:64'],
            'norm.package.display_name' => ['required_with:norm.package', 'nullable', 'string', 'max:255'],
            'norm.package.publisher_name' => ['nullable', 'string', 'max:255'],
            'norm.package.form_code' => ['nullable', 'string', 'max:64'],
            'norm.package.edition' => ['nullable', 'string', 'max:128'],
            'norm.package.language' => ['nullable', 'string', 'max:128'],
            'norm.package.applicable_population' => ['nullable', 'string', 'max:255'],
            'norm.package.source_reference' => ['nullable', 'string', 'max:2048'],
            'norm.package.permitted_use_reference' => ['nullable', 'string', 'max:2048'],
            'norm.tables' => ['sometimes', 'array'],
            'norm.tables.*' => ['required', 'array:metric_code,output_scale,intervals'],
            'norm.tables.*.metric_code' => ['required', 'string', 'max:64'],
            'norm.tables.*.output_scale' => ['required', Rule::in(['percentile_rank', 'stanine'])],
            'norm.tables.*.intervals' => ['required', 'array'],
            'norm.tables.*.intervals.*' => ['required', 'array:min_score,max_score,output_value'],
            'norm.tables.*.intervals.*.min_score' => ['required', 'numeric', 'between:-999999999999.999999,999999999999.999999'],
            'norm.tables.*.intervals.*.max_score' => ['required', 'numeric', 'between:-999999999999.999999,999999999999.999999'],
            'norm.tables.*.intervals.*.output_value' => ['required', 'string', 'max:64'],

        ];

        if (! $this->isFinalOnlyRequest()) {
            return $rules;
        }

        foreach (array_keys($rules) as $field) {
            if (str_starts_with($field, 'norm.')) {
                unset($rules[$field]);
            }
        }
        $rules['norm'] = [
            'sometimes',
            'nullable',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null) {
                    $fail('A final-only Test Setup cannot include a norm package or conversion tables.');
                }
            },
        ];

        return $rules;
    }

    private function isFinalOnlyRequest(): bool
    {
        $metrics = $this->input('instrument.metrics');
        if (! is_array($metrics) || $metrics === []) {
            return false;
        }

        foreach ($metrics as $metric) {
            if (! is_array($metric) || ($metric['rule_kind'] ?? null) !== 'external_final') {
                return false;
            }
        }

        return true;
    }

    public function withValidator(Validator $validator): void
    {

        $validator->after(function (Validator $validator): void {

            $allowed = ['preparation_id', 'expected_preparation_revision', 'idempotency_key', 'instrument', 'norm'];

            foreach (array_diff(array_keys($this->getInputSource()->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }

            foreach ($this->input('norm.tables', []) as $tableIndex => $table) {
                if (! is_array($table) || ($table['output_scale'] ?? null) !== 'percentile_rank') {
                    continue;
                }

                foreach ($table['intervals'] ?? [] as $intervalIndex => $interval) {
                    $value = is_array($interval) ? ($interval['output_value'] ?? null) : null;

                    if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
                        $validator->errors()->add(
                            "norm.tables.{$tableIndex}.intervals.{$intervalIndex}.output_value",
                            'The percentile rank must be a number between 0 and 100.',
                        );
                    }
                }
            }
        });
    }
}
