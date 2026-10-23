<?php

namespace App\AdmissionEngine\Support;

use InvalidArgumentException;

final class RiasecActivityCatalog
{
    /** @var array<string, array{label:string,parent:string}> */
    private const ACTIVITIES = [
        'hardware_tech' => ['label' => 'Hardware & Systems', 'parent' => 'riasec_r'],
        'construction_drafting' => ['label' => 'Drafting & Construction', 'parent' => 'riasec_r'],
        'athletics_sports' => ['label' => 'Athletics & Kinesthetics', 'parent' => 'riasec_r'],
        'software_algorithms' => ['label' => 'Algorithms & Software', 'parent' => 'riasec_i'],
        'scientific_biological' => ['label' => 'Clinical & Health Sciences', 'parent' => 'riasec_i'],
        'mathematical_modeling' => ['label' => 'Quantitative Mathematics', 'parent' => 'riasec_i'],
        'literary_writing' => ['label' => 'Literary & Language Expression', 'parent' => 'riasec_a'],
        'digital_design' => ['label' => 'Digital Media & UI/UX', 'parent' => 'riasec_a'],
        'performing_arts' => ['label' => 'Performing Arts', 'parent' => 'riasec_a'],
        'teaching_pedagogy' => ['label' => 'Classroom Pedagogy', 'parent' => 'riasec_s'],
        'patient_care' => ['label' => 'Patient Care & Empathy', 'parent' => 'riasec_s'],
        'hospitality_service' => ['label' => 'Hospitality & Guest Relations', 'parent' => 'riasec_s'],
        'business_leadership' => ['label' => 'Business Leadership & Management', 'parent' => 'riasec_e'],
        'tourism_operations' => ['label' => 'Tourism Operations & Hosting', 'parent' => 'riasec_e'],
        'sales_marketing' => ['label' => 'Sales & Marketing', 'parent' => 'riasec_e'],
        'records_clerical' => ['label' => 'Office Administration & Records', 'parent' => 'riasec_c'],
        'data_accounting' => ['label' => 'Data & Accounting', 'parent' => 'riasec_c'],
        'inventory_organization' => ['label' => 'Inventory & Organization', 'parent' => 'riasec_c'],
    ];

    /** @return list<array{id:string,label:string,parent:string}> */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::ACTIVITIES as $id => $definition) {
            $definitions[] = ['id' => $id, ...$definition];
        }

        return $definitions;
    }

    /** @param list<string> $activityIds @return array<string,?int> */
    public static function project(array $activityIds, bool $reviewed): array
    {
        $flags = array_fill_keys(['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'], $reviewed ? 0 : null);
        if (! $reviewed && $activityIds !== []) {
            throw new InvalidArgumentException('RIASEC activities cannot be projected unless the assessment is explicitly reviewed.');
        }
        if (count($activityIds) !== count(array_unique($activityIds))) {
            throw new InvalidArgumentException('RIASEC activity identities must be unique.');
        }
        foreach ($activityIds as $id) {
            $parent = self::ACTIVITIES[$id]['parent'] ?? null;
            if ($parent === null) {
                throw new InvalidArgumentException('RIASEC activity ID is not in the approved activity catalog.');
            }
            $flags[$parent] = 1;
        }

        return $flags;
    }

    /** @param list<string> $activityIds @return list<string> */
    public static function validatePreferences(array $activityIds): array
    {
        if (count($activityIds) !== count(array_unique($activityIds))) {
            throw new InvalidArgumentException('Programme interest activity identities must be unique.');
        }
        foreach ($activityIds as $id) {
            if (! isset(self::ACTIVITIES[$id])) {
                throw new InvalidArgumentException('Programme interest contains an unknown RIASEC activity ID.');
            }
        }

        return array_values($activityIds);
    }

    /**
     * Compute the emergent RIASEC percentage vector and top Holland code from reviewed activities.
     *
     * @param  list<string>  $activityIds
     * @return array{
     *     is_reviewed: bool,
     *     scores: array<string, float>,
     *     counts: array<string, int>,
     *     emergent_code: ?string,
     *     emergent_themes: list<string>
     * }
     */
    public static function profileFromActivities(array $activityIds, bool $reviewed): array
    {
        $themeKeys = ['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'];
        $counts = array_fill_keys($themeKeys, 0);

        if ($reviewed) {
            foreach ($activityIds as $id) {
                $parent = self::ACTIVITIES[$id]['parent'] ?? null;
                if ($parent !== null && isset($counts[$parent])) {
                    $counts[$parent]++;
                }
            }
        }

        $scores = [];
        foreach ($counts as $key => $count) {
            // Each theme has 3 activities in the catalog.
            $scores[$key] = $reviewed ? round(($count / 3.0) * 100.0, 1) : 0.0;
        }

        $codeMap = [
            'riasec_r' => 'R',
            'riasec_i' => 'I',
            'riasec_a' => 'A',
            'riasec_s' => 'S',
            'riasec_e' => 'E',
            'riasec_c' => 'C',
        ];

        // Determine top 2 themes by score if reviewed and at least one score > 0
        $emergentCode = null;
        $emergentThemes = [];
        if ($reviewed && array_sum($counts) > 0) {
            $sorted = $counts;
            arsort($sorted);
            $top = array_slice($sorted, 0, 2, true);
            $letters = '';
            foreach ($top as $k => $c) {
                if ($c > 0) {
                    $letters .= $codeMap[$k];
                    $emergentThemes[] = $k;
                }
            }
            $emergentCode = $letters !== '' ? $letters : null;
        }

        return [
            'is_reviewed' => $reviewed,
            'scores' => $scores,
            'counts' => $counts,
            'emergent_code' => $emergentCode,
            'emergent_themes' => $emergentThemes,
        ];
    }
}
