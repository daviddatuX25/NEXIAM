<?php

namespace App\Http\Requests\Consultation;

use App\Admission\Support\GwaScaleNormalizer;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\RiasecActivityCatalog;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class RecordSupplementalEvidenceRequest extends FormRequest
{
    private bool $schemaResolved = false;

    private ?AdmissionBenchmarkSet $schemaSet = null;

    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof AdmissionAttempt
            && app(CapabilityAuthorizer::class)->decideFresh(
                $this->user(),
                CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
                ['institution_id' => (int) $attempt->institution_id, 'office' => 'guidance'],
            )->allowed;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'evidence_source' => ['nullable', 'in:examinee_statement'],
            'vocational_activities' => ['sometimes', 'array'],
            'vocational_activities.*' => ['string', 'distinct', 'max:64'],
            'activities_reviewed' => ['sometimes', 'boolean'],
            'counselor_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'gwa_scale' => ['sometimes', 'nullable', 'string', 'in:percentage_100,ph_1_highest,gpa_4_highest,gpa_5_highest'],
            'gwa' => [
                'nullable',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $scale = (string) ($this->input('gwa_scale') ?: GwaScaleNormalizer::inferScale((float) $value));
                    if (! GwaScaleNormalizer::isValidValueForScale((float) $value, $scale)) {
                        $fail('Enter a valid GWA for the selected grading scale ('.GwaScaleNormalizer::scaleLabel($scale).').');
                    }
                },
            ],
            'benchmark_activation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'schema_digest' => ['sometimes', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [...array_keys($this->rules()), '_token'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'Unexpected evidence field.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $set = $this->selectedSchema();
            if ($set !== null
                && (($this->filled('schema_digest') && ! hash_equals((string) $set->schema_sha256, (string) $this->input('schema_digest')))
                    || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
                    || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest()))) {
                $validator->errors()->add('schema_digest', 'The selected evidence schema or benchmark content changed.');

                return;
            }
            try {
                RiasecActivityCatalog::project(
                    $this->input('vocational_activities', []),
                    $this->boolean('activities_reviewed'),
                );
                if ($set !== null) {
                    app(BenchmarkInputSchema::class)->validateValues($set, $this->input('subject_grades', []));
                }
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('evidence', $exception->getMessage());
            }
        }];
    }

    private function selectedSchema(): ?AdmissionBenchmarkSet
    {
        if (! $this->schemaResolved) {
            $this->schemaResolved = true;
            $attempt = $this->route('attempt');
            $activationQuery = AdmissionBenchmarkActivation::query()
                ->where('institution_id', $attempt->institution_id)
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->with('benchmarkSet.features');
            $activation = $this->filled('benchmark_activation_id')
                ? $activationQuery->whereKey($this->integer('benchmark_activation_id'))->first()
                : $activationQuery->where('is_current', true)->first();
            $this->schemaSet = $activation?->benchmarkSet;
        }

        return $this->schemaSet;
    }
}
