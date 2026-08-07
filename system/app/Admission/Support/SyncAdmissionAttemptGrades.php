<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptGradeValue;
use App\Models\Foundation\IntakeGradeField;

/**
 * Upsert catalog-driven digital grade snapshot values on an attempt (D-086).
 * Does not bump attempt.version — callers own versioning.
 */
final class SyncAdmissionAttemptGrades
{
    /**
     * @param  array<string, mixed>  $grades  field code => numeric value
     * @return list<string> codes written
     */
    public function handle(AdmissionAttempt $attempt, array $grades): array
    {
        if ($grades === []) {
            return [];
        }

        $fields = IntakeGradeField::activeForInstitution((int) $attempt->institution_id)
            ->keyBy('code');

        $changed = [];
        $gwaValue = null;

        foreach ($grades as $code => $value) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            $field = $fields->get($code);
            if ($field === null) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            AdmissionAttemptGradeValue::query()->updateOrCreate(
                [
                    'admission_attempt_id' => $attempt->id,
                    'intake_grade_field_id' => $field->id,
                ],
                [
                    'field_code' => $field->code,
                    'field_label' => $field->label,
                    'value' => $value,
                ],
            );

            $changed[] = $code;

            if ($field->field_kind === IntakeGradeField::KIND_GWA || $field->code === 'GWA') {
                $gwaValue = $value;
            }
        }

        if ($gwaValue !== null) {
            $attempt->forceFill(['gwa' => $gwaValue])->save();
        }

        return $changed;
    }
}
