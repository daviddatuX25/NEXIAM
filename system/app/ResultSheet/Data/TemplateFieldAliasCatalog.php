<?php

namespace App\ResultSheet\Data;

/**
 * Legacy placeholder authority for result sheets (parity with the original
 * Word templates). Modern templates may use any key here; legacy institutional
 * templates (ICAT) use the domain aliases below, which resolve to current
 * aptitude-area codes. Ratings always come from the frozen
 * `score_records.converted_score`, never recomputed.
 */
final class TemplateFieldAliasCatalog
{
    /**
     * Legacy template domain names → current aptitude-area codes.
     * Unknown legacy names (abstract_reasoning, manual_dexterity,
     * perceptual_*) resolve to null and render empty — no score source exists.
     *
     * @return array<string, string|null>
     */
    public static function domainAliases(): array
    {
        return [
            'verbal_aptitude' => 'VERBAL',
            'verbal_reasoning' => 'VERBAL',
            'numerical_aptitude' => 'QUANT',
            'numerical_ability' => 'QUANT',
            'spatial_aptitude' => 'SPATIAL',
            'spatial_awareness' => 'SPATIAL',
            'logical_reasoning' => 'LOGIC',
            'memory_recall' => 'MEMORY',
            'language_usage' => 'LANGUAGE',
            'abstract_reasoning' => null,
            'perceptual_aptitude' => null,
            'perceptual_speed_accuracy' => null,
            'manual_dexterity' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            'applicant_name' => 'Full name of applicant',
            'applicant_reference' => 'Application reference number',
            'applicant_number' => 'Application reference (alias)',
            'applicant_no' => 'Application reference (alias)',
            'family_name' => 'Family / last name',
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'suffix' => 'Name suffix',
            'sex' => 'Sex (M/F)',
            'gwa' => 'General weighted average',
            'course_applied' => 'Preferred program name',
            'course_applied_code' => 'Preferred program code',
            'strand' => 'Senior high strand / previous course',
            'strand_or_prev_course' => 'Strand or previous course',
            'applicant_type' => 'Applicant type (new/transfer/returnee)',
            'exam_date' => 'Examination date',
            'exam_time' => 'Examination start time',
            'exam_date_start' => 'Examination date (start)',
            'exam_date_end' => 'Examination date (end)',
            'exam_time_start' => 'Examination start time',
            'exam_time_end' => 'Examination end time',
            'room_name' => 'Examination room',
            'scores_rows' => 'Scores table rows (per area: raw, converted rating)',
            'overall_pct' => 'Overall score (average of area percentages)',
            'recommended_course' => 'Recommended program name',
            'recommended_course_code' => 'Recommended program code',
            'counselor_comments' => 'Counselor comments',
            'counselor_name' => 'Counselor name',
            'institution_name' => 'Institution name',
            'institution_campus' => 'Institution campus',
            'institution_address' => 'Institution address',
            'institution_contact_number' => 'Institution contact number',
            'institution_email' => 'Institution email',
            'institution_website' => 'Institution website',
            'institution_exam_name' => 'Institution exam name',
            'institution_exam_acronym' => 'Institution exam acronym',
            'examination_name' => 'Examination name',
            'examination_acronym' => 'Examination acronym',
            'guidance_counselor_name' => 'Guidance counselor name',
            'guidance_counselor_title' => 'Guidance counselor title',
            'guidance_counselor_credentials' => 'Guidance counselor credentials',
            'testing_coordinator_name' => 'Testing coordinator name',
            'testing_coordinator_title' => 'Testing coordinator title',
            'testing_coordinator_credentials' => 'Testing coordinator credentials',
            'dean_name' => 'Dean name',
            'dean_title' => 'Dean title',
            'dean_credentials' => 'Dean credentials',
            'registrar_name' => 'Registrar name',
            'registrar_title' => 'Registrar title',
            'registrar_credentials' => 'Registrar credentials',
            'vp_academic_affairs_name' => 'VP Academic Affairs name',
            'vp_academic_affairs_title' => 'VP Academic Affairs title',
            'vp_academic_affairs_credentials' => 'VP Academic Affairs credentials',
            'campus_director_name' => 'Campus Director name',
            'campus_director_title' => 'Campus Director title',
            'campus_director_credentials' => 'Campus Director credentials',
            'college_president_name' => 'College President name',
            'college_president_title' => 'College President title',
            'college_president_credentials' => 'College President credentials',
        ];
    }

    /**
     * Placeholders that must exist for the template to be usable.
     *
     * @return list<string>
     */
    public static function required(): array
    {
        return ['applicant_reference'];
    }

    /**
     * @return list<string>
     */
    public static function recommended(): array
    {
        return ['applicant_name', 'scores_rows', 'overall_pct'];
    }

    /**
     * All static (non-domain, non-course-check) placeholder keys.
     *
     * @return list<string>
     */
    public static function staticKeys(): array
    {
        $keys = [
            'applicant_name', 'applicant_reference', 'applicant_number', 'applicant_no',
            'family_name', 'first_name', 'middle_name', 'suffix',
            'sex', 'gwa', 'course_applied', 'course_applied_code', 'strand',
            'strand_or_prev_course', 'applicant_type',
            'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
            'exam_time_start', 'exam_time_end', 'room_name',
            'scores_rows', 'overall_pct',
            'recommended_course', 'recommended_course_code',
            'counselor_comments', 'counselor_name',
            'institution_name', 'institution_campus', 'institution_address',
            'institution_contact_number', 'institution_email', 'institution_website',
            'institution_exam_name', 'institution_exam_acronym',
            'examination_name', 'examination_acronym',
        ];

        foreach (['guidance_counselor', 'testing_coordinator', 'dean', 'registrar',
            'vp_academic_affairs', 'campus_director', 'college_president'] as $role) {
            foreach (['name', 'title', 'credentials'] as $field) {
                $keys[] = $role.'_'.$field;
            }
        }

        return $keys;
    }
}
