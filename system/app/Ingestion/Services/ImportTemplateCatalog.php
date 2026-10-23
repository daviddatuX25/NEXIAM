<?php

namespace App\Ingestion\Services;

use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldKey;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Foundation\IntakeRequirementLevel;
use App\Models\Ingestion\ImportBatch;
use Illuminate\Validation\ValidationException;

final class ImportTemplateCatalog
{
    /** @return array{type:string, academic_cycle:array{id:int,label:string}, test_kit_version_id:?int, is_final_only:bool, revision:string, fields:list<array<string,mixed>>, default_headers:list<string>} */
    public function schema(string $type, Institution $institution, AcademicCycle $cycle, ?TestKitVersion $kit, ?ExamSession $session = null): array
    {
        $contract = ResolvedImportContract::compile($institution, $cycle, $kit);
        $policyVersion = $contract->policyVersion;
        $policies = IntakeFieldPolicy::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('version', $policyVersion)
            ->orderBy('sort_order')
            ->get()
            ->keyBy(fn (IntakeFieldPolicy $policy): string => $policy->field_key->value);
        $fields = [];
        $requiredHeaders = [];

        if ($type !== ImportBatch::TYPE_SCORES_ONLY) {
            foreach (IntakeFieldKey::cases() as $key) {
                $policy = $policies->get($key->value);
                $level = $key->isSystemMandatory()
                    ? IntakeRequirementLevel::SystemMandatory
                    : ($policy?->requirement_level ?? $key->defaultLevel());
                if (! $policy?->is_active && $policy !== null && ! $key->isSystemMandatory()) {
                    $level = IntakeRequirementLevel::Disabled;
                }
                if (in_array($level, [IntakeRequirementLevel::SystemMandatory, IntakeRequirementLevel::InstitutionRequired], true)) {
                    $requiredHeaders[] = $key->value;
                }
                if ($level === IntakeRequirementLevel::Disabled) {
                    continue;
                }
                $fields[] = [
                    'header' => $key->value,
                    'label' => $policy?->label ?: $key->label(),
                    'group' => $key->fieldGroup(),
                    'required' => in_array($level, [IntakeRequirementLevel::SystemMandatory, IntakeRequirementLevel::InstitutionRequired], true),
                    'requirement_reason' => $level === IntakeRequirementLevel::SystemMandatory ? 'Required by the institutional intake floor.' : ($level === IntakeRequirementLevel::InstitutionRequired ? 'Required by the current Academic Year and Semester field policy.' : null),
                    'description' => $this->descriptionFor($key->value),
                    'source' => in_array($key, [IntakeFieldKey::CoursePreference1, IntakeFieldKey::CoursePreference2, IntakeFieldKey::CoursePreference3], true) ? 'Active Academic Program catalog' : 'Applicant application form',
                    'format' => $this->formatFor($key->value),
                    'example' => $this->exampleFor($key->value),
                    'aliases' => array_values(array_filter(
                        $key->importAliases(),
                        fn (string $alias): bool => SpreadsheetParser::normalizeHeader($alias) === $key->value,
                    )),
                ];
            }
        }

        $scores = [];
        if ($type !== ImportBatch::TYPE_APPLICANTS_ONLY) {
            $metrics = TestInstrumentMetric::query()
                ->whereIn('id', array_values($contract->metricCodeToId))
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'unit', 'precision_scale']);
            foreach ($metrics as $metric) {
                $header = strtoupper($metric->code);
                $minimum = $contract->metricCodeToMinScore[$header] ?? null;
                $maximum = $contract->metricCodeToMaxScore[$header] ?? null;
                if ($contract->finalOnly) {
                    $requiredFinalColumns = $type !== ImportBatch::TYPE_MULTI_SESSION
                        && in_array($header, $contract->requiredMetricCodes, true);
                    $scores[] = [
                        'header' => $header,
                        'label' => $metric->name ?: $header,
                        'group' => 'scores',
                        'required' => $requiredFinalColumns,
                        'requirement_reason' => $requiredFinalColumns
                            ? 'The exact final-only profile requires every declared percentile-rank output.'
                            : 'Optional for demographic-only multi-session rows; if scoring data is entered, provide the complete final-only contract.',
                        'description' => 'Enter the external percentile rank declared for this metric. This is not a locally calculated score.',
                        'source' => 'Authorized external assessment record',
                        'format' => 'Percentile rank from 0 to 100 with at most '.($metric->precision_scale ?? 6).' decimal places',
                        'example' => 0,
                        'aliases' => [],
                        'min' => 0,
                        'max' => 100,
                        'precision_scale' => $metric->precision_scale,
                        'output_scale' => 'percentile_rank',
                    ];
                    $sourceHeader = $contract->metricCodeToSourceHeader[$header];
                    $scores[] = [
                        'header' => $sourceHeader,
                        'label' => $header.' source reference',
                        'group' => 'sources',
                        'required' => $requiredFinalColumns,
                        'requirement_reason' => $requiredFinalColumns
                            ? 'Required to attribute the external final for this metric.'
                            : 'Optional for demographic-only multi-session rows; if scoring data is entered, provide this per-metric reference.',
                        'description' => 'Identify the source record that reports this percentile rank; no shared source fallback is used.',
                        'source' => 'Authorized external assessment record',
                        'format' => 'Text source reference',
                        'example' => 'Record reference',
                        'aliases' => [],
                        'value_type' => 'text',
                    ];

                    if ($requiredFinalColumns) {
                        $requiredHeaders[] = $header;
                        $requiredHeaders[] = $sourceHeader;
                    }

                    continue;
                }
                $format = $minimum !== null && $maximum !== null
                    ? 'Raw numeric score from '.$minimum.' to '.$maximum
                    : ($minimum !== null ? 'Raw numeric score, minimum '.$minimum : ($maximum !== null ? 'Raw numeric score, maximum '.$maximum : 'Raw numeric score'));
                $format .= $metric->precision_scale === 0
                    ? '; integer '.$metric->unit
                    : '; '.$metric->unit.' with at most '.($metric->precision_scale ?? 6).' decimal places';
                $scores[] = [
                    'header' => $header,
                    'label' => $metric->name ?: $header,
                    'group' => 'scores',
                    'required' => false,
                    'requirement_reason' => $type === ImportBatch::TYPE_SCORES_ONLY
                        ? 'Score columns are optional; each score-only row must contain at least one accepted score.'
                        : 'This direct raw score is optional in the combined applicant and scoring template.',
                    'description' => 'Enter the direct raw score recorded for this metric. Composite and derived metrics are not accepted.',
                    'source' => 'Applicant’s raw-score worksheet or authorized external raw-score record',
                    'format' => $format,
                    'example' => $minimum ?? 0,
                    'aliases' => [],
                    ...($minimum !== null ? ['min' => (float) $minimum] : []),
                    ...($maximum !== null ? ['max' => (float) $maximum] : []),
                    'unit' => $metric->unit,
                    'precision_scale' => $metric->precision_scale,
                ];
            }
            if ($scores === []) {
                throw ValidationException::withMessages(['test_kit_version_id' => 'The selected Test Kit has no accepted direct score fields.']);
            }
            if ($type === ImportBatch::TYPE_SCORES_ONLY) {
                array_unshift($fields, [
                    'header' => 'applicant_number', 'label' => 'Applicant Number', 'group' => 'identity',
                    'required' => true, 'requirement_reason' => 'Required to match scores to an existing applicant.',
                    'description' => 'Cycle-issued applicant reference. This is not an LRN or an equivalent identifier.',
                    'source' => 'Existing NEXIAM applicant record', 'format' => 'cycle-issued applicant number', 'example' => 'AY26-00001',
                    'aliases' => [],
                ]);
                array_unshift($requiredHeaders, 'applicant_number');
            }
            $fields = [...$fields, ...$scores];
        }

        $defaultHeaders = $type === ImportBatch::TYPE_SCORES_ONLY
            ? ['applicant_number', ...array_column($scores, 'header')]
            : ($type === ImportBatch::TYPE_ALL_IN_ONE
                ? [...array_column(array_filter($fields, fn (array $field): bool => $field['group'] !== 'scores'), 'header'), ...array_column($scores, 'header')]
                : [...$requiredHeaders, ...array_values(array_column(array_filter($fields, fn (array $field): bool => $field['group'] !== 'scores' && ! $field['required']), 'header'))]);
        $policyRevision = (string) $policyVersion;
        $identity = [
            'type' => $type,
            'cycle' => (int) $cycle->id,
            'policy_revision' => $policyRevision,
            'fields' => $fields,
            'kit_id' => $kit?->id,
            'kit_revision' => $kit?->revision,
            'session_id' => $session?->id,
            'session_version' => $session?->version,
            'session_kit_version_id' => $session?->test_kit_version_id,
        ];
        $revision = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));

        return [
            'type' => $type,
            'academic_cycle' => ['id' => (int) $cycle->id, 'label' => $cycle->name],
            'is_final_only' => $contract->finalOnly,
            'revision' => $revision,
            'fields' => array_values($fields),
            'default_headers' => array_values(array_unique($defaultHeaders)),
        ];
    }

    /** @param list<string> $headers */
    public function csvHeaders(array $headers): string
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, $headers);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false) {
            throw new \RuntimeException('The template CSV could not be generated.');
        }

        return "\xEF\xBB\xBF".$csv;
    }

    private function formatFor(string $header): string
    {
        return match ($header) {
            'birthdate' => 'YYYY-MM-DD date',
            'sex' => 'Value accepted by the applicant application form',
            'contact_email' => 'Email address',
            'contact_phone' => 'Phone number as entered on the application form',
            'zip_code' => 'Postal or ZIP code',
            'applicant_type' => 'Applicant classification accepted by the application form',
            'course_preference_1', 'course_preference_2', 'course_preference_3' => 'Active program code',
            default => 'Text',
        };
    }

    private function exampleFor(string $header): string
    {
        return match ($header) {
            'first_name' => 'Maria',
            'middle_name' => 'L.',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'birthdate' => '2008-04-12',
            'sex' => 'Female',
            'contact_email' => 'maria@example.test',
            'contact_phone' => '+63 917 000 0000',
            'address_line' => '123 Sample Street',
            'city' => 'Taguig',
            'province' => 'Metro Manila',
            'zip_code' => '1630',
            'strand' => 'STEM',
            'last_school_enrolled' => 'Example Senior High School',
            'applicant_type' => 'New',
            'course_preference_1', 'course_preference_2', 'course_preference_3' => 'BSIT',
            default => '',
        };
    }

    private function descriptionFor(string $header): string
    {
        return match ($header) {
            'first_name' => 'Applicant given name.',
            'middle_name' => 'Applicant middle name or initial, when provided.',
            'last_name' => 'Applicant family name.',
            'suffix' => 'Name suffix, when applicable.',
            'birthdate' => 'Applicant date of birth.',
            'sex' => 'Applicant sex value as recorded in the application form.',
            'contact_email' => 'Applicant email address for contact.',
            'contact_phone' => 'Applicant contact phone number.',
            'address_line' => 'Applicant street or address line.',
            'city' => 'Applicant city or municipality.',
            'province' => 'Applicant province or region.',
            'zip_code' => 'Applicant postal or ZIP code.',
            'strand' => 'Senior high school strand, when applicable.',
            'last_school_enrolled' => 'Name of the applicant’s last school enrolled.',
            'applicant_type' => 'Applicant classification used by the application form.',
            'course_preference_1' => 'Applicant first-choice active academic program code.',
            'course_preference_2' => 'Applicant second-choice active academic program code, if provided.',
            'course_preference_3' => 'Applicant third-choice active academic program code, if provided.',
            default => 'Applicant information accepted by the current intake field policy.',
        };
    }
}
