<?php

namespace App\AdmissionEngine\Support;

final class PhilippineDegreeHollandMap
{
    /**
     * Authoritative built-in Holland RIASEC targets for all 11 ISPSC Tagudin Campus degree programs,
     * grounded in CHED Policies, Standards, and Guidelines (CMOs) and DepEd Career Guidance (CGP) frameworks.
     *
     * @var array<string, array{
     *     code: string,
     *     name: string,
     *     primary: string,
     *     secondary: string,
     *     cmo_reference: string,
     *     cgp_strand_alignment: string,
     *     domain_focus: string,
     *     priority_strands: list<string>,
     *     vector: array<string, int>
     * }>
     */
    private const TARGETS = [
        'BSIT' => [
            'code' => 'IR',
            'name' => 'Investigative · Realistic',
            'primary' => 'riasec_i',
            'secondary' => 'riasec_r',
            'cmo_reference' => 'CHED CMO No. 25, s. 2015 (Policies, Standards and Guidelines for IT Education)',
            'cgp_strand_alignment' => 'STEM / TVL-ICT',
            'priority_strands' => ['STEM', 'TVL-ICT'],
            'domain_focus' => 'Computing, Software Algorithms, Systems Architecture & Hardware Engineering',
            'vector' => ['riasec_r' => 1, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BSEd' => [
            'code' => 'SA',
            'name' => 'Social · Artistic',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_a',
            'cmo_reference' => 'CHED CMO No. 75, s. 2017 (Policies, Standards and Guidelines for Bachelor of Secondary Education)',
            'cgp_strand_alignment' => 'HUMSS / Academic Track',
            'priority_strands' => ['HUMSS', 'GAS'],
            'domain_focus' => 'Secondary Classroom Pedagogy, Instructional Design & Educational Communication',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 1, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BSEd-Eng' => [
            'code' => 'SA',
            'name' => 'Secondary Education major in English',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_a',
            'cmo_reference' => 'CHED CMO No. 75, s. 2017 (Policies, Standards and Guidelines for Bachelor of Secondary Education)',
            'cgp_strand_alignment' => 'HUMSS / Academic Track',
            'priority_strands' => ['HUMSS', 'GAS'],
            'domain_focus' => 'Secondary English Language Pedagogy, World Literature & Communicative Instruction',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 1, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BSEd-Math' => [
            'code' => 'SI',
            'name' => 'Secondary Education major in Mathematics',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_i',
            'cmo_reference' => 'CHED CMO No. 75, s. 2017 (Policies, Standards and Guidelines for Bachelor of Secondary Education)',
            'cgp_strand_alignment' => 'STEM / Academic Track',
            'priority_strands' => ['STEM', 'GAS'],
            'domain_focus' => 'Secondary Mathematics Pedagogy, Calculus, Geometry & Mathematical Modeling',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BEEd' => [
            'code' => 'SA',
            'name' => 'Social · Artistic',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_a',
            'cmo_reference' => 'CHED CMO No. 74, s. 2017 (Policies, Standards and Guidelines for Bachelor of Elementary Education)',
            'cgp_strand_alignment' => 'HUMSS / General Academic',
            'priority_strands' => ['HUMSS', 'GAS'],
            'domain_focus' => 'Foundational Child Development, Primary Pedagogy & Creative Instruction',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 1, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BPEd' => [
            'code' => 'RS',
            'name' => 'Realistic · Social',
            'primary' => 'riasec_r',
            'secondary' => 'riasec_s',
            'cmo_reference' => 'CHED CMO No. 80, s. 2017 (Policies, Standards and Guidelines for Bachelor of Physical Education)',
            'cgp_strand_alignment' => 'Sports Track / TVL',
            'priority_strands' => ['Sports Track', 'TVL', 'GAS'],
            'domain_focus' => 'Sports Kinesthetics, Athletic Coaching, Physical Conditioning & Health Leadership',
            'vector' => ['riasec_r' => 1, 'riasec_i' => 0, 'riasec_a' => 0, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BSBA' => [
            'code' => 'EC',
            'name' => 'Enterprising · Conventional',
            'primary' => 'riasec_e',
            'secondary' => 'riasec_c',
            'cmo_reference' => 'CHED CMO No. 17, s. 2017 (Policies, Standards and Guidelines for Business Administration)',
            'cgp_strand_alignment' => 'ABM (Accountancy, Business and Management)',
            'priority_strands' => ['ABM', 'GAS'],
            'domain_focus' => 'Business Administration, Executive Leadership, Marketing Strategy & Operations Management',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 1, 'riasec_c' => 1],
        ],
        'BSEntrep' => [
            'code' => 'EC',
            'name' => 'Enterprising · Conventional',
            'primary' => 'riasec_e',
            'secondary' => 'riasec_c',
            'cmo_reference' => 'CHED CMO No. 18, s. 2017 (Policies, Standards and Guidelines for BS Entrepreneurship)',
            'cgp_strand_alignment' => 'ABM (Accountancy, Business and Management)',
            'priority_strands' => ['ABM', 'GAS'],
            'domain_focus' => 'Enterprise Incubation, Venture Creation, Commercial Development & Fiscal Oversight',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 1, 'riasec_c' => 1],
        ],
        'BSMath' => [
            'code' => 'IC',
            'name' => 'Investigative · Conventional',
            'primary' => 'riasec_i',
            'secondary' => 'riasec_c',
            'cmo_reference' => 'CHED CMO No. 48, s. 2017 (Policies, Standards and Guidelines for BS Mathematics)',
            'cgp_strand_alignment' => 'STEM (Science, Technology, Engineering and Mathematics)',
            'priority_strands' => ['STEM', 'GAS'],
            'domain_focus' => 'Pure and Applied Mathematics, Statistical Modeling, Numerical Computation & Data Rigor',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 0, 'riasec_c' => 1],
        ],
        'BA-EL' => [
            'code' => 'AS',
            'name' => 'Artistic · Social',
            'primary' => 'riasec_a',
            'secondary' => 'riasec_s',
            'cmo_reference' => 'CHED CMO No. 24, s. 2017 (Policies, Standards and Guidelines for BA English Language)',
            'cgp_strand_alignment' => 'HUMSS (Humanities and Social Sciences)',
            'priority_strands' => ['HUMSS', 'GAS'],
            'domain_focus' => 'Linguistic Analysis, English Discourse, Literary Exposition & Intercultural Communication',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 1, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BA-Psych' => [
            'code' => 'SI',
            'name' => 'Social · Investigative',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_i',
            'cmo_reference' => 'CHED CMO No. 34, s. 2017 (Policies, Standards and Guidelines for Psychology)',
            'cgp_strand_alignment' => 'HUMSS / STEM',
            'priority_strands' => ['HUMSS', 'STEM', 'GAS'],
            'domain_focus' => 'Psychological Sciences, Clinical Assessment, Human Behavior & Psychometrics',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BASS' => [
            'code' => 'SI',
            'name' => 'Social · Investigative',
            'primary' => 'riasec_s',
            'secondary' => 'riasec_i',
            'cmo_reference' => 'CHED Social Science Discipline Standards / DepEd CGP HUMSS Social Sciences Alignment',
            'cgp_strand_alignment' => 'HUMSS (Humanities and Social Sciences)',
            'priority_strands' => ['HUMSS', 'GAS'],
            'domain_focus' => 'Sociological Inquiry, Community Development, Public Policy & Historical Analysis',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 1, 'riasec_e' => 0, 'riasec_c' => 0],
        ],
        'BPA' => [
            'code' => 'ES',
            'name' => 'Enterprising · Social',
            'primary' => 'riasec_e',
            'secondary' => 'riasec_s',
            'cmo_reference' => 'CHED CMO No. 06, s. 2010 (Policies and Standards for Public Administration)',
            'cgp_strand_alignment' => 'HUMSS / ABM',
            'priority_strands' => ['HUMSS', 'ABM', 'GAS'],
            'domain_focus' => 'Public Administration, Government Affairs, Bureaucratic Leadership & Civil Service',
            'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 0, 'riasec_s' => 1, 'riasec_e' => 1, 'riasec_c' => 0],
        ],
    ];

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     primary: string,
     *     secondary: string,
     *     cmo_reference: string,
     *     cgp_strand_alignment: string,
     *     domain_focus: string,
     *     vector: array<string, int>
     * }|null
     */
    public static function targetFor(string $programCode): ?array
    {
        $target = self::TARGETS[$programCode] ?? null;

        return $target === null ? null : [...$target, ...self::cmoSource($target['cmo_reference'])];
    }

    /**
     * @return list<string>
     */
    public static function priorityStrandsFor(string $programCode): array
    {
        $target = self::targetFor($programCode);

        return $target['priority_strands'] ?? [];
    }

    /**
     * Evaluate curriculum and strand alignment between an applicant's graduated SHS strand
     * and the CHED CMO priority strands for the selected degree program.
     *
     * @return array{
     *     status: string,
     *     label: string,
     *     badge_variant: string,
     *     summary: string,
     *     cmo_reference: ?string,
     *     aligned_strands: list<string>,
     *     applicant_strand: ?string
     * }
     */
    /**
     * @param  list<string>|null  $overridePriorityStrands
     */
    public static function evaluateStrandAlignment(
        ?string $applicantStrand,
        string $programCode,
        string $applicantType = 'new',
        ?array $overridePriorityStrands = null,
    ): array {
        $target = self::targetFor($programCode);
        if ($target === null && $overridePriorityStrands === null) {
            return [
                'status' => 'uncalibrated',
                'summary' => 'Curriculum benchmark is not defined for this program.',
                'label' => 'Uncalibrated',
                'badge_variant' => 'outline',
                'cmo_reference' => null,
                ...self::cmoSource(null),
                'aligned_strands' => [],
                'applicant_strand' => $applicantStrand,
            ];
        }

        $alignedStrands = ($overridePriorityStrands !== null)
            ? array_values($overridePriorityStrands)
            : ($target['priority_strands'] ?? []);
        $cmoReference = $target['cmo_reference'] ?? null;

        if ($applicantType === 'transferee') {
            $priorProgram = ($applicantStrand !== null && trim($applicantStrand) !== '')
                ? trim($applicantStrand)
                : null;

            return [
                'status' => 'transferee_articulation',
                'label' => 'Transferee Credit Articulation',
                'badge_variant' => 'secondary',
                'summary' => 'College transferee ('.($priorProgram ?? 'prior program not recorded').'). Credit equivalency and residency are evaluated by the receiving department via official TOR.',
                'cmo_reference' => $cmoReference,
                ...self::cmoSource($cmoReference),
                'aligned_strands' => $alignedStrands,
                'applicant_strand' => $priorProgram,
            ];
        }

        if ($alignedStrands === []) {
            return [
                'status' => 'open_track',
                'label' => 'Open Track · No Constraint',
                'badge_variant' => 'outline',
                'summary' => 'This program has an open feeder policy; applicants from any Senior High School track or strand are eligible without prerequisite bridging.',
                'cmo_reference' => $cmoReference,
                ...self::cmoSource($cmoReference),
                'aligned_strands' => [],
                'applicant_strand' => $applicantStrand,
            ];
        }

        if ($applicantStrand === null || trim($applicantStrand) === '') {
            return [
                'status' => 'not_recorded',
                'label' => 'Strand Not Recorded',
                'badge_variant' => 'secondary',
                'summary' => 'Applicant Senior High School strand was not recorded.',
                'cmo_reference' => $cmoReference,
                ...self::cmoSource($cmoReference),
                'aligned_strands' => $alignedStrands,
                'applicant_strand' => null,
            ];
        }

        $normalizedApplicant = strtoupper(trim($applicantStrand));
        $isDirectMatch = false;
        foreach ($alignedStrands as $strand) {
            $normalizedAligned = strtoupper(trim($strand));
            if (
                $normalizedApplicant === $normalizedAligned
                || str_contains($normalizedApplicant, $normalizedAligned)
                || str_contains($normalizedAligned, $normalizedApplicant)
                || ($normalizedAligned === 'TVL-ICT' && (str_contains($normalizedApplicant, 'ICT') || str_contains($normalizedApplicant, 'INFORMATION AND COMMUNICATIONS TECHNOLOGY')))
                || ($normalizedAligned === 'ABM' && str_contains($normalizedApplicant, 'ACCOUNTANCY'))
                || ($normalizedAligned === 'STEM' && (str_contains($normalizedApplicant, 'SCIENCE') || str_contains($normalizedApplicant, 'STEM')))
                || ($normalizedAligned === 'HUMSS' && str_contains($normalizedApplicant, 'HUMANITIES'))
            ) {
                $isDirectMatch = true;
                break;
            }
        }

        if ($isDirectMatch) {
            return [
                'status' => 'directly_aligned',
                'label' => 'Directly Aligned',
                'badge_variant' => 'default',
                'summary' => "Applicant completed strand ({$applicantStrand}) directly satisfies the CHED CMO priority alignment for {$programCode}.",
                'cmo_reference' => $cmoReference,
                ...self::cmoSource($cmoReference),
                'aligned_strands' => $alignedStrands,
                'applicant_strand' => $applicantStrand,
            ];
        }

        if (str_contains($normalizedApplicant, 'GAS') || str_contains($normalizedApplicant, 'GENERAL ACADEMIC')) {
            return [
                'status' => 'general_academic',
                'label' => 'General Academic Track',
                'badge_variant' => 'secondary',
                'summary' => 'Applicant completed General Academic Strand (GAS); eligible with standard course orientation.',
                'cmo_reference' => $cmoReference,
                ...self::cmoSource($cmoReference),
                'aligned_strands' => $alignedStrands,
                'applicant_strand' => $applicantStrand,
            ];
        }

        return [
            'status' => 'bridging_recommended',
            'label' => 'Non-Traditional Strand · Bridging Recommended',
            'badge_variant' => 'outline',
            'summary' => "Applicant graduated from a non-aligned strand ({$applicantStrand}). Cross-strand admission is accommodated under university policy; bridging foundation units may be assigned by the academic department.",
            'cmo_reference' => $cmoReference,
            ...self::cmoSource($cmoReference),
            'aligned_strands' => $alignedStrands,
            'applicant_strand' => $applicantStrand,
        ];
    }

    /**
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     primary: string,
     *     secondary: string,
     *     cmo_reference: string,
     *     cgp_strand_alignment: string,
     *     domain_focus: string,
     *     vector: array<string, int>
     * }>
     */
    public static function allTargets(): array
    {
        $targets = [];
        foreach (self::TARGETS as $programCode => $target) {
            $targets[$programCode] = [...$target, ...self::cmoSource($target['cmo_reference'])];
        }

        return $targets;
    }

    /**
     * @return array{cmo_short: ?string, cmo_url: string}
     */
    private static function cmoSource(?string $reference): array
    {
        if ($reference === null || preg_match('/CMO No\\.\\s*(\\d+),\\s*s\\.\\s*(\\d{4})/', $reference, $matches) !== 1) {
            return ['cmo_short' => null, 'cmo_url' => 'https://ched.gov.ph/ched-memorandum-orders/'];
        }

        $cmoNumber = (int) $matches[1];
        $year = (int) $matches[2];
        $url = match ([$cmoNumber, $year]) {
            [25, 2015] => 'https://ched.gov.ph/wp-content/uploads/2017/10/CMO-no.-25-s.-2015.pdf',
            [34, 2017] => 'https://legacy.ched.gov.ph/wp-content/uploads/2017/10/CMO-34-s-2017.pdf',
            [27, 2017] => 'https://ched.gov.ph/wp-content/uploads/2017/10/CMO-27-s-2017.pdf',
            [17, 2017] => 'https://ched.gov.ph/wp-content/uploads/2017/10/CMO-17-s-2017.pdf',
            [15, 2017] => 'https://ched.gov.ph/wp-content/uploads/2017/10/CMO-15-s-2017.pdf',
            [74, 2017], [75, 2017] => 'https://ched.gov.ph/wp-content/uploads/2017/11/CMO-No.-74-s.-2017.pdf',
            default => 'https://ched.gov.ph/ched-memorandum-orders/',
        };

        return [
            'cmo_short' => "CHED CMO No. {$matches[1]}, s. {$year}",
            'cmo_url' => $url,
        ];
    }

    /**
     * Evaluate congruence between an applicant's emergent Holland profile and a program target.
     *
     * @param  array<string, float|int>  $applicantScores  (percentage scores 0-100 per theme)
     * @return array{
     *     alignment_level: string,
     *     summary: string,
     *     primary_match: bool,
     *     secondary_match: bool
     * }
     */
    public static function evaluateCongruence(array $applicantScores, string $programCode): array
    {
        $target = self::targetFor($programCode);
        if ($target === null) {
            return [
                'alignment_level' => 'uncalibrated',
                'summary' => 'Target profile is not defined for this program.',
                'primary_match' => false,
                'secondary_match' => false,
            ];
        }

        $primaryScore = (float) ($applicantScores[$target['primary']] ?? 0);
        $secondaryScore = (float) ($applicantScores[$target['secondary']] ?? 0);

        $primaryActive = $primaryScore >= 33.3;
        $secondaryActive = $secondaryScore >= 33.3;

        if ($primaryActive && $secondaryActive) {
            $level = 'High Congruence';
            $summary = "Applicant interests directly align with both primary ({$target['primary']}) and secondary ({$target['secondary']}) dimensions of {$programCode}.";
        } elseif ($primaryActive || $secondaryActive) {
            $matched = $primaryActive ? $target['primary'] : $target['secondary'];
            $level = 'Moderate Congruence';
            $summary = "Applicant interests align with the {$matched} dimension of {$programCode}.";
        } else {
            $level = 'Exploratory Fit';
            $summary = 'Applicant has differing vocational interests; guidance discussion recommended.';
        }

        return [
            'alignment_level' => $level,
            'summary' => $summary,
            'primary_match' => $primaryActive,
            'secondary_match' => $secondaryActive,
        ];
    }
}
