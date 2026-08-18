<?php

namespace App\ResultSheet\Support;

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

        foreach ([1 => 0, 2 => 1] as $slot => $index) {
            $data = $applicants[$index] ?? ($useSampleData ? $sample : null);
            $suffix = $slot === 1 ? '' : '_2';

            if ($data === null) {
                foreach ($this->slotKeys() as $key) {
                    $replacements[$key.$suffix] = '';
                }

                continue;
            }

            foreach ($this->slotKeys() as $key) {
                $replacements[$key.$suffix] = (string) ($data[$key] ?? '');
            }

            // Legacy data arrays carry name/reference; expose both spellings.
            $replacements['applicant_name'.$suffix] = (string) ($data['name'] ?? ($data['applicant_name'] ?? ''));
            $replacements['applicant_reference'.$suffix] = (string) ($data['reference'] ?? ($data['applicant_reference'] ?? ''));

            // Sex short form.
            $sex = strtolower((string) ($data['sex'] ?? ''));
            $replacements['sex'.$suffix] = match (true) {
                $sex === 'male' || $sex === 'm' => 'M',
                $sex === 'female' || $sex === 'f' => 'F',
                default => $sex,
            };

            $replacements['applicant_number'.$suffix] = $replacements['applicant_reference'.$suffix];
            $replacements['applicant_no'.$suffix] = $replacements['applicant_reference'.$suffix];

            $replacements['scores_rows'.$suffix] = $this->buildScoresRows($data['scores'] ?? []);
            $replacements['overall_pct'.$suffix] = (string) ($data['overall_pct'] ?? 0);

            // Per-domain + legacy aliases.
            $scoresBySlug = $this->scoresBySlug($data['scores'] ?? []);
            foreach ($areas as $code => $name) {
                $slug = $this->areaSlug($name);
                $score = $scoresBySlug[$slug] ?? null;

                $value = $score['converted'] ?? '';
                $replacements[$slug.$suffix] = $value;
                $replacements[$slug.'_rating'.$suffix] = $score['rating'] ?? $value;
                $replacements[$slug.'_raw'.$suffix] = $score['raw'] ?? '';
                $replacements[$slug.'_pct'.$suffix] = $score['pct'] ?? '';
            }

            foreach (TemplateFieldAliasCatalog::domainAliases() as $alias => $areaCode) {
                $areaName = $areaCode === null ? null : ($areas[$areaCode] ?? null);
                $score = $areaName === null ? null : ($scoresBySlug[$this->areaSlug($areaName)] ?? null);

                if ($alias === 'general_ability') {
                    $replacements[$alias.$suffix] = $replacements['overall_pct'.$suffix];
                    $replacements[$alias.'_rating'.$suffix] = '';
                    continue;
                }

                $value = $score['converted'] ?? '';
                $replacements[$alias.$suffix] = $value;
                $replacements[$alias.'_rating'.$suffix] = $score['rating'] ?? $value;
                $replacements[$alias.'_raw'.$suffix] = $score['raw'] ?? '';
                $replacements[$alias.'_pct'.$suffix] = $score['pct'] ?? '';
            }

            // Course recommendation checkmarks.
            $recommendedCode = strtoupper((string) ($data['recommended_course_code'] ?? ''));
            foreach (Program::query()->where('is_active', true)->get(['code']) as $program) {
                $checkKey = strtoupper($program->code).'_check'.$suffix;
                $replacements[$checkKey] = $recommendedCode === strtoupper($program->code) ? '✔' : '';
            }
        }

        return $this->mergeInstitutionAndPersonnel($replacements);
    }

    /**
     * Plain-text variant for DOCX TemplateProcessor injection: scores_rows is
     * emptied (per-domain placeholders fill DOCX tables) and values are
     * HTML-stripped with {{ }} escaped.
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

            $clean = strip_tags($value);
            $docx[$key] = str_replace(['{{', '}}'], ['{ {', '} }'], $clean);
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

        foreach (['scores_rows_2' => 'scores-rows-placeholder-2', 'scores_rows' => 'scores-rows-placeholder'] as $key => $class) {
            $rows = $replacements[$key] ?? '';

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
        }

        return $content;
    }

    /**
     * @param  array<int, array{domain?: string, raw?: int|float|null, max?: int|float|null, converted?: string|null, pct?: int|null}>  $scores
     */
    public function buildScoresRows(array $scores): string
    {
        if ($scores === []) {
            return '';
        }

        $rows = '';

        foreach ($scores as $score) {
            $rows .= '<tr>'
                .'<td>'.e((string) ($score['domain'] ?? '')).'</td>'
                .'<td class="text-center">'.e((string) ($score['raw'] ?? '')).'</td>'
                .'<td class="text-center">'.e((string) ($score['converted'] ?? '')).'</td>'
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
                'converted' => 'Above Average',
                'rating' => 'Above Average',
                'pct' => 85,
            ];
        }

        return [
            'name' => 'Juan M. Dela Cruz',
            'family_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'M',
            'suffix' => '',
            'sex' => 'male',
            'gwa' => '1.75',
            'course_applied' => 'Bachelor of Science in Nursing',
            'course_applied_code' => 'BSN',
            'strand' => 'STEM',
            'strand_or_prev_course' => 'STEM',
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
            'applicant_name', 'applicant_reference', 'family_name', 'first_name',
            'middle_name', 'suffix', 'sex', 'gwa', 'course_applied',
            'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type',
            'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
            'exam_time_start', 'exam_time_end', 'room_name',
            'recommended_course', 'recommended_course_code', 'counselor_comments', 'counselor_name',
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
