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
            'general_ability' => 'LOGIC',
            'abstract_reasoning' => null,
            'perceptual_aptitude' => 'MEMORY',
            'perceptual_speed_accuracy' => 'MEMORY',
            'manual_dexterity' => 'LANGUAGE',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return [
            'applicant_number' => 'applicant_reference',
            'applicant_no' => 'applicant_reference',
            'examinee_no' => 'examinee_number',
            'seq_no' => 'sequence_number',
            'ref' => 'applicant_reference',
            'ref_no' => 'applicant_reference',
            'app_no' => 'applicant_reference',
            'app_num' => 'applicant_reference',
            'name' => 'applicant_name',
            'photo' => 'applicant_photo_2x2',
            'photo_2x2' => 'applicant_photo_2x2',
            'applicant_photo' => 'applicant_photo_2x2',
            'qr' => 'verification_qr',
            'qr_code' => 'verification_qr',
            'verification_url' => 'verification_qr',
            'full_name' => 'applicant_name',
            'fn' => 'first_name',
            'first' => 'first_name',
            'ln' => 'family_name',
            'last_name' => 'family_name',
            'surname' => 'family_name',
            'mn' => 'middle_name',
            'mi' => 'middle_name',
            'middle' => 'middle_name',
            'sfx' => 'suffix',
            'gender' => 'sex',
            'prog_code' => 'course_applied_code',
            'course_code' => 'course_applied_code',
            'cc' => 'course_applied_code',
            'program' => 'course_applied',
            'course' => 'course_applied',
            'shs_strand' => 'strand',
            'school_short' => 'last_school_short',
            'history_short' => 'strand_or_prev_course_short',
            'type' => 'applicant_type',
            'app_type' => 'applicant_type',
            'ed' => 'exam_date',
            'date' => 'exam_date',
            'et' => 'exam_time',
            'time' => 'exam_time',
            'rm' => 'room_name',
            'room' => 'room_name',
            'room_no' => 'room_name',
            'scores' => 'scores_rows',
            'table' => 'scores_rows',
            'score_table' => 'scores_rows',
            'overall' => 'overall_pct',
            'pct' => 'overall_pct',
            'total' => 'overall_pct',
            'gen_ability' => 'overall_pct',
            'rec_code' => 'recommended_course_code',
            'rc_code' => 'recommended_course_code',
            'rec_program' => 'recommended_course',
            'rec_course' => 'recommended_course',
            'remarks' => 'counselor_comments',
            'comments' => 'counselor_comments',
            'notes' => 'counselor_comments',
            'verbal' => 'verbal_aptitude',
            'va' => 'verbal_aptitude',
            'numerical' => 'numerical_aptitude',
            'quant' => 'numerical_aptitude',
            'na' => 'numerical_aptitude',
            'spatial' => 'spatial_aptitude',
            'sa' => 'spatial_aptitude',
            'perceptual' => 'perceptual_aptitude',
            'pa' => 'perceptual_aptitude',
            'dexterity' => 'manual_dexterity',
            'md' => 'manual_dexterity',
            'counselor' => 'counselor_name',
            'gc' => 'counselor_name',
            'tc' => 'testing_coordinator_name',
            'coordinator' => 'testing_coordinator_name',
            'dean' => 'dean_name',
            'reg' => 'registrar_name',
            'registrar' => 'registrar_name',
            'pres' => 'college_president_name',
            'president' => 'college_president_name',
        ];
    }

    public static function canonical(string $key): string
    {
        return self::aliases()[$key] ?? $key;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function aliasMapByCanonical(): array
    {
        $aliasesByCanonical = [];

        foreach (self::aliases() as $alias => $canonical) {
            $aliasesByCanonical[$canonical][] = $alias;
        }

        return $aliasesByCanonical;
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            'applicant_name' => 'Full name of applicant',
            'applicant_photo_2x2' => 'Applicant 2x2 photo or verified intake image',
            'verification_qr' => '300-DPI verification or candidate locator QR code',
            'applicant_reference' => 'Application reference number',
            'examinee_number' => '5-digit examinee number (e.g. 00001)',
            'sequence_number' => 'Compact sequence number (e.g. 1)',
            'applicant_number' => 'Application reference (alias)',
            'applicant_no' => 'Application reference (alias)',
            'family_name' => 'Family / last name',
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'suffix' => 'Name suffix',
            'sex' => 'Sex (M/F)',
            'gwa' => 'General weighted average (0–100 institutional scale)',
            'gwa_original' => 'Original reported GWA on school grading scale',
            'gwa_scale' => 'Original GWA grading scale label',
            'gwa_display' => 'GWA on 0–100 scale with original reported value when converted',
            'course_applied' => 'Preferred program name',
            'course_applied_code' => 'Preferred program code',
            'last_school_enrolled' => 'Last school — full name',
            'last_school_short' => 'Last school — abbreviation (e.g. Ilocos Sur College → ISC). Known abbreviation, otherwise generated initials.',
            'strand' => 'Senior high strand / previous course',
            'strand_or_prev_course' => 'Strand or previous course',
            'strand_or_prev_course_short' => 'Strand / previous course — abbreviation (e.g. Humanities and Social Sciences → HUMSS). Known abbreviation, otherwise generated initials.',
            'applicant_type' => 'Applicant type (new/transfer/returnee)',
            'exam_date' => 'Examination date',
            'exam_time' => 'Examination start time',
            'exam_date_start' => 'Examination date (start)',
            'exam_date_end' => 'Examination date (end)',
            'exam_time_start' => 'Examination start time',
            'exam_time_end' => 'Examination end time',
            'room_name' => 'Examination room',
            'scores_rows' => 'Scores table rows (per area: raw, converted rating)',
            'overall_pct' => 'Explicit overall/composite score when defined by the instrument',
            'score_table_raw_heading' => 'Builder score table raw-score heading',
            'score_table_final_heading' => 'Builder score table final-score heading',
            'overall_heading' => 'Builder overall-score heading when an explicit overall exists',
            'general_ability' => 'Overall general ability score (alias of overall percentage)',
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
            'examinee_number', 'sequence_number',
            'family_name', 'first_name', 'middle_name', 'suffix',
            'sex', 'gwa', 'gwa_original', 'gwa_scale', 'gwa_display', 'course_applied', 'course_applied_code', 'last_school_enrolled', 'last_school_short', 'strand',
            'strand_or_prev_course', 'strand_or_prev_course_short', 'applicant_type',
            'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
            'exam_time_start', 'exam_time_end', 'room_name',
            'scores_rows', 'overall_pct', 'overall_heading', 'score_table_raw_heading', 'score_table_final_heading',
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

        return array_values(array_unique(array_merge($keys, array_keys(self::aliases()))));
    }
}
