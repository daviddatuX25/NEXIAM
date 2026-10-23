<?php

namespace App\ResultSheet\Support;

use App\Admission\Support\ApplicantReference;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use Illuminate\Support\Str;

/**
 * Result-sheet placeholder engine (legacy parity). Builds the full replacement
 * map for one or two applicants: identity, exam details, per-domain
 * raw/converted/pct, scores_rows table injection, overall, course checkmarks,
 * institution display fields, and personnel signatories. Ratings always come
 * from the frozen converted value — never recomputed.
 */
final class ResultSheetPlaceholderService
{
    /**
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array<string, string>
     */
    public function buildReplacements(array $applicants, bool $useSampleData = false): array
    {
        $sample = $this->sampleData();
        $replacements = [];
        $areas = $this->activeAreas();

        $data = $applicants[0] ?? ($useSampleData ? $sample : null);

        if ($data === null) {
            foreach ($this->slotKeys() as $key) {
                $replacements[$key] = '';
            }

            return $this->applyAliases($this->mergeInstitutionAndPersonnel($replacements));
        }

        foreach ($this->slotKeys() as $key) {
            $replacements[$key] = (string) ($data[$key] ?? '');
        }
        $shortFields = TemplateShortValue::fields(
            $data['last_school_enrolled'] ?? null,
            $data['strand'] ?? null,
            ($data['applicant_type'] ?? '') === 'new' ? 'history' : 'previous-course',
        );
        foreach ($shortFields as $key => $value) {
            $replacements[$key] = $value;
        }
        // Legacy data arrays carry name/reference; expose both spellings.
        $replacements['applicant_name'] = (string) ($data['name'] ?? ($data['applicant_name'] ?? ''));
        $replacements['applicant_reference'] = (string) ($data['reference'] ?? ($data['applicant_reference'] ?? ''));

        // Sex short form.
        $sex = strtolower((string) ($data['sex'] ?? ''));
        $replacements['sex'] = match (true) {
            $sex === 'male' || $sex === 'm' => 'M',
            $sex === 'female' || $sex === 'f' => 'F',
            default => $sex,
        };

        $replacements['reference_code'] = $replacements['applicant_reference'];
        $replacements['applicant_number'] = $replacements['applicant_reference'];
        $replacements['applicant_no'] = $replacements['applicant_reference'];
        $replacements['examinee_number'] = (string) ($data['examinee_number'] ?? (ApplicantReference::paddedDigits($replacements['applicant_reference']) ?? $replacements['applicant_reference']));
        $replacements['sequence_number'] = (string) ($data['sequence_number'] ?? (ApplicantReference::compactNumber($replacements['applicant_reference']) ?? $replacements['applicant_reference']));
        $replacements['applicant_photo_2x2'] = (string) ($data['photo_path'] ?? ($data['applicant_photo_2x2'] ?? ''));
        $ref = $replacements['applicant_reference'] !== '' ? $replacements['applicant_reference'] : 'SAMPLE-REF';
        $replacements['verification_qr'] = (string) ($data['verification_qr'] ?? url("/verify/{$ref}"));

        $replacements['scores_rows'] = $this->buildScoresRows($data['scores'] ?? []);
        $replacements['overall_pct'] = ($data['overall_pct'] ?? null) === null ? '' : (string) $data['overall_pct'];
        $replacements['overall_heading'] = $replacements['overall_pct'] === '' ? '' : 'Overall Score';

        // Per-domain + legacy aliases.
        $scores = is_array($data['scores'] ?? null) ? $data['scores'] : [];
        $scoresBySlug = $this->scoresBySlug($scores);
        $finalOnly = $scores !== [] && collect($scores)->every(static fn (array $score): bool => ($score['scale'] ?? null) === 'percentile_rank');
        $hasRawScores = collect($scores)->contains(static fn (array $score): bool => trim((string) ($score['raw'] ?? '')) !== '');
        $replacements['score_table_raw_heading'] = $hasRawScores ? 'Raw Score' : '';
        $replacements['score_table_final_heading'] = $finalOnly ? 'Percentile rank' : 'Descriptive Rating';
        foreach ($areas as $code => $name) {
            $slug = $this->areaSlug($name);
            $score = $scoresBySlug[$slug] ?? null;

            $value = (string) ($score['converted'] ?? '');
            $replacements[$slug] = $value;
            $replacements[$slug.'_rating'] = $value;
            $replacements[$slug.'_raw'] = $score['raw'] ?? '';
            $replacements[$slug.'_pct'] = ($score['scale'] ?? null) === 'percentile_rank'
                ? (string) ($score['converted'] ?? '')
                : ($score['pct'] ?? '');
        }

        foreach (TemplateFieldAliasCatalog::domainAliases() as $alias => $areaCode) {
            $areaName = $areaCode === null ? null : ($areas[$areaCode] ?? null);
            $score = $areaName === null ? null : ($scoresBySlug[$this->areaSlug($areaName)] ?? null);

            if ($alias === 'general_ability') {
                if ($score !== null && ($score['converted'] ?? '') !== '') {
                    $value = (string) $score['converted'];
                    $replacements[$alias] = $value;
                    $replacements[$alias.'_rating'] = $value;
                    $replacements[$alias.'_raw'] = (string) ($score['raw'] ?? '');
                    $replacements[$alias.'_pct'] = ($score['scale'] ?? null) === 'percentile_rank'
                        ? (string) ($score['converted'] ?? '')
                        : (string) ($score['pct'] ?? '');
                } else {
                    $replacements[$alias] = $replacements['overall_pct'];
                    $replacements[$alias.'_rating'] = '';
                    $replacements[$alias.'_raw'] = '';
                    $replacements[$alias.'_pct'] = $replacements['overall_pct'];
                }

                continue;
            }

            $value = (string) ($score['converted'] ?? '');
            $replacements[$alias] = $value;
            $replacements[$alias.'_rating'] = $value;
            $replacements[$alias.'_raw'] = $score['raw'] ?? '';
            $replacements[$alias.'_pct'] = ($score['scale'] ?? null) === 'percentile_rank'
                ? (string) ($score['converted'] ?? '')
                : ($score['pct'] ?? '');
        }

        // Course recommendation checkmarks.
        $recommendedCode = strtoupper((string) ($data['recommended_course_code'] ?? ''));
        foreach (Program::query()->where('is_active', true)->get(['code']) as $program) {
            $isRecommended = $recommendedCode === strtoupper($program->code);
            $checkmark = $isRecommended ? '✔' : '';
            $replacements[strtoupper($program->code).'_check'] = $checkmark;
            $replacements[Str::slug($program->code, '_').'_check'] = $checkmark;
        }

        return $this->applyAliases($this->mergeInstitutionAndPersonnel($replacements));
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function applyAliases(array $replacements): array
    {
        foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
            if (isset($replacements[$canonical]) && ! isset($replacements[$alias])) {
                $replacements[$alias] = $replacements[$canonical];
            }

            foreach (['_rating', '_raw', '_pct'] as $suffix) {
                $canonicalVariation = $canonical.$suffix;
                $aliasVariation = $alias.$suffix;

                if (isset($replacements[$canonicalVariation]) && ! isset($replacements[$aliasVariation])) {
                    $replacements[$aliasVariation] = $replacements[$canonicalVariation];
                }
            }
        }

        return $replacements;
    }

    /**
     * Plain-text variant for DOCX TemplateProcessor injection: scores_rows is
     * emptied (per-domain placeholders fill DOCX tables) and literal {{ }}
     * sequences in replacement data are neutralized. DocxFiller XML-escapes
     * the resulting values during insertion.
     *
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array<string, string>
     */
    public function buildDocxReplacements(array $applicants): array
    {
        $docx = [];

        foreach ($this->buildReplacements($applicants) as $key => $value) {
            if (Str::startsWith($key, 'scores_rows')) {
                $docx[$key] = '';

                continue;
            }

            $docx[$key] = str_replace(['{{', '}}'], ['{ {', '} }'], $value);
        }

        return $docx;
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    public function renderRaw(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        $rows = $replacements['scores_rows'] ?? '';
        $class = 'scores-rows-placeholder';
        $content = preg_replace_callback(
            '/<\s*tr\s+class\s*=\s*["\']'.preg_quote($class, '/').'["\'][^>]*>.*?<\s*\/\s*tr\s*>/s',
            fn () => $rows ?: '<tr class="'.$class.'"><td colspan="3"></td></tr>',
            $content,
            1,
            $count
        );

        if (($count ?? 0) === 0) {
            $content = preg_replace_callback(
                '/(<\s*tbody\s+class\s*=\s*["\']'.preg_quote($class, '/').'["\'][^>]*>)(.*?)(<\s*\/\s*tbody\s*>)/s',
                fn ($matches) => $matches[1]."\n".($rows ?: '<tr><td colspan="3"></td></tr>')."\n".$matches[3],
                $content,
                1
            );
        }

        return $content;
    }

    /**
     * @param  array<int, array{domain?: string, raw?: string|int|float|null, max?: int|float|null, converted?: string|null, pct?: int|null, scale?: string|null}>  $scores
     */
    public function buildScoresRows(array $scores): string
    {
        if ($scores === []) {
            return '';
        }

        $rows = '';
        foreach ($scores as $score) {

            $final = (string) ($score['converted'] ?? '');
            $rows .= '<tr>'
                .'<td>'.e((string) ($score['domain'] ?? '')).'</td>'
                .'<td class="text-center">'.e((string) ($score['raw'] ?? '')).'</td>'
                .'<td class="text-center">'.e($final).'</td>'
                .'</tr>';
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleData(): array
    {
        $areas = $this->activeAreas();
        $scores = [];

        foreach ($areas as $code => $name) {
            $scores[] = [
                'domain' => $name,
                'raw' => 85,
                'max' => 100,
                'converted' => '85',
                'rating' => '85',
                'pct' => 85,
                'scale' => 'percentile_rank',
            ];
        }

        return [
            'name' => 'Juan M. Dela Cruz',
            'family_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'M',
            'suffix' => '',
            'sex' => 'male',
            'gwa' => '91.40',
            'gwa_original' => '91.40',
            'gwa_scale' => '0–100%',
            'gwa_display' => '91.40',
            'course_applied' => 'Bachelor of Science in Nursing',
            'course_applied_code' => 'BSN',
            'last_school_enrolled' => 'Ilocos Sur College',
            'last_school_short' => 'ISC',
            'strand' => 'STEM',
            'strand_or_prev_course' => 'STEM',
            'strand_or_prev_course_short' => 'STEM',
            'school_short' => 'ISC',
            'history_short' => 'STEM',
            'applicant_type' => 'new',
            'reference' => '2026-000123',
            'exam_date' => 'August 20, 2026',
            'exam_time' => '8:00 AM',
            'exam_date_start' => 'August 20, 2026',
            'exam_date_end' => 'August 20, 2026',
            'exam_time_start' => '8:00 AM',
            'exam_time_end' => '12:00 PM',
            'room_name' => 'Room 204',
            'scores' => $scores,
            'overall_pct' => 85,
            'recommended_course' => 'Bachelor of Science in Nursing',
            'recommended_course_code' => 'BSN',
            'counselor_comments' => 'Recommended for admission.',
            'counselor_name' => 'Raveena Galope, RGC, RPm',
        ];
    }

    /**
     * @return array<string, string> area code → area name (active only)
     */
    private function activeAreas(): array
    {
        return AptitudeArea::query()
            ->where('is_active', true)

            ->orderBy('id')
            ->pluck('name', 'code')
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     * @return array<string, array<string, mixed>>
     */
    private function scoresBySlug(array $scores): array
    {
        $bySlug = [];

        foreach ($scores as $score) {
            $bySlug[$this->areaSlug((string) ($score['domain'] ?? ''))] = $score;
        }

        return $bySlug;
    }

    /**
     * @return list<string>
     */
    private function slotKeys(): array
    {
        return [
            'applicant_name', 'applicant_reference', 'examinee_number', 'sequence_number', 'family_name', 'first_name',
            'middle_name', 'suffix', 'sex', 'gwa', 'gwa_original', 'gwa_scale', 'gwa_display', 'course_applied',
            'course_applied_code', 'last_school_enrolled', 'last_school_short', 'strand', 'strand_or_prev_course', 'strand_or_prev_course_short', 'applicant_type',
            'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
            'exam_time_start', 'exam_time_end', 'room_name',
            'overall_pct', 'overall_heading', 'score_table_raw_heading', 'score_table_final_heading',
            'recommended_course', 'recommended_course_code', 'counselor_comments', 'counselor_name',
            'applicant_photo_2x2', 'verification_qr',
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function mergeInstitutionAndPersonnel(array $replacements): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        $config = config('result-sheet.institution', []);

        $institutionName = $institution?->name ?? '';

        $map = [
            'institution_name' => $institutionName,
            'institution_campus' => $config['campus'] ?? '',
            'institution_address' => $config['address'] ?? '',
            'institution_contact_number' => $config['contact_number'] ?? '',
            'institution_email' => $config['email'] ?? '',
            'institution_website' => $config['website'] ?? '',
            'institution_exam_name' => $config['exam_name'] ?? '',
            'institution_exam_acronym' => $config['exam_acronym'] ?? '',
        ];

        $map['institution_contact'] = $map['institution_contact_number'];
        $map['examination_name'] = $map['institution_exam_name'];
        $map['examination_acronym'] = $map['institution_exam_acronym'];

        $personnel = config('result-sheet.personnel', []);

        foreach ($personnel as $role => $defaults) {
            foreach (['name', 'title', 'credentials'] as $field) {
                $map[$role.'_'.$field] = (string) ($defaults[$field] ?? '');
            }
        }

        return array_merge($replacements, $map);
    }

    private function areaSlug(string $name): string
    {
        return str_replace('-', '_', Str::slug($name, '_'));
    }
}
