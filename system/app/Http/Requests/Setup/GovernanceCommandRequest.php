<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GovernanceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'program_id' => ['sometimes', 'integer', 'exists:programs,id'],
            'cycle_id' => ['sometimes', 'integer', 'exists:academic_cycles,id'],
            'content' => ['sometimes', 'array:tiers,authoring'],
            'content.tiers' => ['required_with:content', 'array:1,2,3,4,5'],
            'content.tiers.1' => ['required_with:content', 'array:values'],
            'content.tiers.2' => ['required_with:content', 'array:values'],
            'content.tiers.3' => ['required_with:content', 'array:values'],
            'content.tiers.4' => ['required_with:content', 'array:values'],
            'content.tiers.5' => ['required_with:content', 'array:values'],
            'content.tiers.*.values' => ['required', 'array'],
            'content.tiers.*.values.*' => ['nullable'],
            'content.authoring' => ['sometimes', 'array:version,ranges,preferred_strands,interests,preparation,interest_activities,activities_reviewed'],
            'content.authoring.version' => ['required_with:content.authoring', 'integer', 'in:1,2'],
            'content.authoring.ranges' => ['required_with:content.authoring', 'array:honors,regular,cutoff'],
            'content.authoring.ranges.honors' => ['present_with:content.authoring', 'array'],
            'content.authoring.ranges.regular' => ['present_with:content.authoring', 'array'],
            'content.authoring.ranges.cutoff' => ['present_with:content.authoring', 'array'],
            'content.authoring.ranges.*.*' => ['array:0,1'],
            'content.authoring.ranges.*.*.0' => ['present', 'nullable', 'numeric'],
            'content.authoring.ranges.*.*.1' => ['present', 'nullable', 'numeric'],
            'content.authoring.preferred_strands' => ['present_with:content.authoring', 'nullable', 'array'],
            'content.authoring.preferred_strands.*' => ['string', 'max:64'],
            'content.authoring.interests' => ['present_with:content.authoring', 'nullable', 'array'],
            'content.authoring.interests.*' => ['string', Rule::in(['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'])],
            'content.authoring.interest_activities' => ['sometimes', 'nullable', 'array'],
            'content.authoring.interest_activities.*' => ['string', Rule::in([
                'hardware_tech', 'construction_drafting', 'athletics_sports',
                'software_algorithms', 'scientific_biological', 'mathematical_modeling',
                'literary_writing', 'digital_design', 'performing_arts',
                'teaching_pedagogy', 'patient_care', 'hospitality_service',
                'business_leadership', 'tourism_operations', 'sales_marketing',
                'records_clerical', 'data_accounting', 'inventory_organization',
            ])],
            'content.authoring.activities_reviewed' => ['sometimes', 'boolean'],
            'content.authoring.preparation' => ['sometimes', 'array'],
            'content.authoring.preparation.*' => ['array:version,common_range,before,applied_values,reviewed'],
            'content.authoring.preparation.*.version' => ['required', 'integer', 'in:1'],
            'content.authoring.preparation.*.common_range' => ['required', 'array:0,1'],
            'content.authoring.preparation.*.common_range.0' => ['required', 'numeric'],
            'content.authoring.preparation.*.common_range.1' => ['required', 'numeric'],
            'content.authoring.preparation.*.before' => ['required', 'array:honors,regular,cutoff'],
            'content.authoring.preparation.*.before.honors' => ['present', 'nullable', 'array:0,1'],
            'content.authoring.preparation.*.before.regular' => ['present', 'nullable', 'array:0,1'],
            'content.authoring.preparation.*.before.cutoff' => ['present', 'nullable', 'array:0,1'],
            'content.authoring.preparation.*.before.*.0' => ['nullable', 'numeric'],
            'content.authoring.preparation.*.before.*.1' => ['nullable', 'numeric'],
            'content.authoring.preparation.*.applied_values' => ['required', 'array:honors,regular,cutoff'],
            'content.authoring.preparation.*.applied_values.honors' => ['required', 'numeric'],
            'content.authoring.preparation.*.applied_values.regular' => ['required', 'numeric'],
            'content.authoring.preparation.*.applied_values.cutoff' => ['required', 'numeric'],
            'content.authoring.preparation.*.reviewed' => ['required', 'accepted'],
            'note_content' => ['sometimes', 'string', 'max:20000'],
            'revision_id' => ['sometimes', 'integer'],
            'source' => ['sometimes', 'in:active,draft'],
            'request_id' => ['sometimes', 'string', 'max:128'],
            'input' => ['sometimes', 'array:attempt_id,gwa,strand,aptitudes,subject_grades,vocational_activities,activities_reviewed'],
            'input.attempt_id' => ['nullable', 'integer'],
            'input.gwa' => ['nullable', 'numeric'],
            'input.strand' => ['nullable', 'string', 'max:64'],
            'input.aptitudes' => ['array'],
            'input.aptitudes.*' => ['nullable', 'numeric'],
            'input.subject_grades' => ['array'],
            'input.subject_grades.*' => ['nullable', 'numeric'],
            'input.vocational_activities' => ['array'],
            'input.vocational_activities.*' => ['string', 'distinct', 'max:64'],
            'input.activities_reviewed' => ['boolean'],
            'expected_version' => ['sometimes', 'integer', 'min:0'],
            'expected_current_activation_id' => ['sometimes', 'nullable', 'integer'],
            'expected_content_sha256' => ['sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'expected_source_digest' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'schema_cutover' => ['sometimes', 'boolean', 'accepted'],
            'expected_predecessor_schema_digest' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'recovery_source_set_id' => ['sometimes', 'integer', 'min:1'],
            'expected_source_snapshot_digest' => ['sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'expected_source_activation_id' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['sometimes', 'string', 'max:128'],
            'source_revision_id' => ['sometimes', 'integer'],
            'revision_ids_by_program' => ['sometimes', 'array'],
            'revision_ids_by_program.*' => ['integer'],
            'benchmark_set_id' => ['sometimes', 'integer'],
            'topic' => ['sometimes', Rule::in(['benchmark', 'quota', 'advising'])],
            'subject' => ['sometimes', 'string', 'max:255'],
            'benchmark_revision_id' => ['sometimes', 'nullable', 'integer'],
            'quota_change_id' => ['sometimes', 'nullable', 'integer'],
            'thread_id' => ['sometimes', 'integer'],
            'body' => ['sometimes', 'string', 'max:8000'],
            'status' => ['sometimes', Rule::in(['open', 'closed', 'read_only'])],
            'through_message_id' => ['sometimes', 'nullable', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'before_message_id' => ['sometimes', 'nullable', 'integer'],
            'note_id' => ['sometimes', 'integer'],
            'publication_id' => ['sometimes', 'integer'],
            'target_program_id' => ['sometimes', 'integer', 'exists:programs,id'],
            'target_cycle_id' => ['sometimes', 'integer', 'exists:academic_cycles,id'],
            'attempt_id' => ['sometimes', 'integer', 'exists:admission_attempts,id'],
            'recipient_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $allowed = [
                '_token', '_method', 'program_id', 'cycle_id', 'content', 'expected_version',
                'expected_current_activation_id', 'expected_content_sha256', 'reason',
                'idempotency_key', 'source_revision_id', 'revision_ids_by_program',
                'benchmark_set_id', 'topic', 'subject', 'benchmark_revision_id',
                'quota_change_id', 'thread_id', 'body', 'status', 'through_message_id',
                'page', 'per_page', 'before_message_id', 'note_id', 'publication_id',
                'target_program_id', 'target_cycle_id', 'attempt_id', 'recipient_id',
                'recovery_source_set_id', 'expected_source_snapshot_digest', 'expected_source_activation_id',
                'expected_source_digest', 'schema_cutover', 'expected_predecessor_schema_digest',
                'note_content', 'revision_id', 'source', 'request_id', 'input',
            ];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                $validator->errors()->add($unknown, 'Unexpected request field.');
            }
            $recoveryFields = ['recovery_source_set_id', 'expected_source_snapshot_digest', 'expected_source_activation_id'];
            $providedRecoveryFields = array_intersect($recoveryFields, array_keys($this->all()));
            if ($providedRecoveryFields !== [] && count($providedRecoveryFields) !== count($recoveryFields)) {
                $validator->errors()->add('recovery_source_set_id', 'Recovery publication requires source set, snapshot digest, and source activation identity.');
            }
            $schemaFields = ['schema_cutover', 'expected_predecessor_schema_digest'];
            $providedSchemaFields = array_intersect($schemaFields, array_keys($this->all()));
            if ($providedSchemaFields !== [] && count($providedSchemaFields) !== count($schemaFields)) {
                $validator->errors()->add('schema_cutover', 'Schema publication requires its explicit cutover review and exact predecessor digest.');
            }
        }];
    }
}
