<?php

namespace App\Admission\Queries;

use App\Models\Admission\AdmissionAttempt;

/**
 * Registrar attempt detail — single-record authority for P-REG-02.
 */
final class ShowAdmissionAttemptQuery
{
    /**
     * @return array<string, mixed>
     */
    public function handle(AdmissionAttempt $attempt): array
    {
        $attempt->loadMissing([
            'identity:id,first_name,middle_name,last_name,suffix,birthdate,sex,email,phone',
            'academicCycle:id,code,name',
            'gradeValues:id,admission_attempt_id,field_code,value',
            'privacyPolicy:id,title',
        ]);

        $identity = $attempt->identity;
        $name = trim(implode(' ', array_filter([
            $identity?->first_name,
            $identity?->middle_name,
            $identity?->last_name,
            $identity?->suffix,
        ])));

        return [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'version' => $attempt->version,
            'applicant_name' => $name !== '' ? $name : 'Unknown',
            'identity' => [
                'first_name' => $identity?->first_name,
                'middle_name' => $identity?->middle_name,
                'last_name' => $identity?->last_name,
                'suffix' => $identity?->suffix,
                'birthdate' => $identity?->birthdate?->toDateString(),
                'sex' => $identity?->sex,
                'email' => $identity?->email,
                'phone' => $identity?->phone,
            ],
            'contact_email' => $attempt->contact_email ?? $identity?->email,
            'contact_phone' => $attempt->contact_phone ?? $identity?->phone,
            'applicant_type' => $attempt->applicant_type,
            'last_school_enrolled' => $attempt->last_school_enrolled,
            'strand' => $attempt->strand,
            'gwa' => $attempt->gwa !== null ? (string) $attempt->gwa : null,
            'program_preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'preferred_program_code' => $attempt->program_preference_1_code
                ?? $attempt->preferred_program_code,
            'address' => [
                'line' => $attempt->address_line,
                'city' => $attempt->city,
                'province' => $attempt->province,
                'zip_code' => $attempt->zip_code,
            ],
            'grades' => $attempt->gradeValues
                ->map(fn ($g): array => [
                    'code' => $g->field_code,
                    'value' => (string) $g->value,
                ])
                ->values()
                ->all(),
            'correction_notes' => $attempt->correction_notes,
            'dismissal_reason' => $attempt->dismissal_reason,
            'cycle' => [
                'code' => $attempt->academicCycle?->code,
                'name' => $attempt->academicCycle?->name,
            ],
            'privacy_policy_title' => $attempt->privacyPolicy?->title,
            'submitted_at' => $attempt->created_at?->toIso8601String(),
            'accepted_at' => $attempt->accepted_at?->toIso8601String(),
            'dismissed_at' => $attempt->dismissed_at?->toIso8601String(),
            'archived_at' => $attempt->archived_at?->toIso8601String(),
        ];
    }
}
