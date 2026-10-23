<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTestInstrumentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $profile = $this->input('profile');

        if (! is_array($profile)) {
            return;
        }

        $this->merge([
            'profile' => $profile + [
                'material_requirement' => 'unresolved',
                'administration_procedure_reference' => null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $versionedEdit = $this->route('version') !== null;

        return [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => [$versionedEdit ? 'required' : 'sometimes', 'integer', 'min:1'],
            'profile' => [
                'required',
                'array:code,display_name,publisher_name,form_code,edition,language,'.
                'source_reference,permitted_use_reference,material_requirement,'.
                'administration_procedure_reference',
            ],
            'profile.code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'profile.display_name' => ['required', 'string', 'max:255'],
            'profile.publisher_name' => ['nullable', 'string', 'max:255'],
            'profile.form_code' => ['nullable', 'string', 'max:64'],
            'profile.edition' => ['nullable', 'string', 'max:128'],
            'profile.language' => ['nullable', 'string', 'max:32'],
            'profile.source_reference' => ['nullable', 'string', 'max:500'],
            'profile.permitted_use_reference' => ['nullable', 'string', 'max:500'],
            'profile.material_requirement' => ['required', Rule::in(['unresolved', 'paper_backed', 'no_paper'])],
            'profile.administration_procedure_reference' => ['nullable', 'string', 'max:500'],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['array'],
            'tables.*.min_score' => ['nullable', 'numeric'],
            'tables.*.max_score' => ['nullable', 'numeric'],
            'tables.*.output_value' => ['nullable', 'numeric'],
            'subtests' => ['present', 'array', 'max:40'],
            'subtests.*' => ['required', 'array:code,name,display_order,response_mode,item_order_policy,items'],
            'subtests.*.code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'subtests.*.name' => ['required', 'string', 'max:255'],
            'subtests.*.display_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'subtests.*.response_mode' => ['required', Rule::in(['keyed_items', 'recorded_measure'])],
            'subtests.*.item_order_policy' => ['required', Rule::in(['fixed', 'within_subtest_permutable'])],
            'subtests.*.items' => ['present', 'array', 'max:500'],
            'subtests.*.items.*' => ['required', 'array:item_code,local_number,display_order,is_scored,option_tokens'],
            'subtests.*.items.*.item_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'subtests.*.items.*.local_number' => ['required', 'string', 'max:32'],
            'subtests.*.items.*.display_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'subtests.*.items.*.is_scored' => ['required', 'boolean'],
            'subtests.*.items.*.option_tokens' => ['present', 'array', 'max:10'],
            'subtests.*.items.*.option_tokens.*' => ['required', 'string', 'max:32'],
            'metrics' => ['present', 'array', 'max:100'],
            'metrics.*' => ['required', 'array:code,name,rule_kind,subtest_code,unit,min_raw_value,max_raw_value,precision_scale,is_required,required_output_scales,procedure_reference,construct_aptitude_area_id,input_codes,input_contract_json,formula_expression,formula_ast_json,item_codes'],
            'metrics.*.input_contract_json' => ['sometimes', 'nullable', 'array:allowed_sources,required_resources,source_requirements'],
            'metrics.*.input_contract_json.allowed_sources' => ['required_with:metrics.*.input_contract_json', 'array'],
            'metrics.*.input_contract_json.allowed_sources.*' => ['required', 'string'],
            'metrics.*.input_contract_json.required_resources' => ['sometimes', 'array'],
            'metrics.*.input_contract_json.required_resources.*' => ['required', 'string'],
            'metrics.*.input_contract_json.source_requirements' => ['required_with:metrics.*.input_contract_json', 'array'],
            'metrics.*.input_contract_json.source_requirements.*' => ['required', 'array:unit,identity_requirements'],
            'metrics.*.input_contract_json.source_requirements.*.unit' => ['required', 'string', 'max:64'],
            'metrics.*.input_contract_json.source_requirements.*.identity_requirements' => ['required', 'array'],
            'metrics.*.input_contract_json.source_requirements.*.identity_requirements.*' => ['required', 'string'],
            'metrics.*.code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'metrics.*.name' => ['required', 'string', 'max:255'],
            'metrics.*.rule_kind' => ['required', Rule::in(['correct_item_count', 'sum_raw_metrics', 'formula_raw_metrics', 'recorded_measure', 'external_final'])],
            'metrics.*.subtest_code' => ['nullable', 'string', 'max:32'],
            'metrics.*.unit' => ['nullable', 'string', 'max:64'],
            'metrics.*.min_raw_value' => ['nullable', 'numeric'],
            'metrics.*.max_raw_value' => ['nullable', 'numeric'],
            'metrics.*.precision_scale' => ['nullable', 'integer', 'min:0', 'max:6'],
            'metrics.*.is_required' => ['required', 'boolean'],
            'metrics.*.required_output_scales' => ['present', 'array', 'max:3'],
            'metrics.*.required_output_scales.*' => ['required', Rule::in(['percentile_rank', 'stanine', 'category'])],
            'metrics.*.procedure_reference' => ['nullable', 'string', 'max:500'],
            'metrics.*.construct_aptitude_area_id' => ['nullable', 'integer', 'exists:aptitude_areas,id'],
            'metrics.*.input_codes' => ['present', 'array', 'max:100'],
            'metrics.*.formula_expression' => ['nullable', 'string', 'max:2048'],
            'metrics.*.formula_ast_json' => ['sometimes', 'nullable', 'array'],
            'metrics.*.item_codes' => ['sometimes', 'nullable', 'array', 'max:500'],
            'metrics.*.item_codes.*' => ['required', 'string', 'max:64'],
            'metrics.*.input_codes.*' => ['required', 'string', 'max:32'],
        ];
    }
}
