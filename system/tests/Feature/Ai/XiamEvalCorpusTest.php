<?php

declare(strict_types=1);

use App\Ai\Context\PageCapabilityPackRegistry;

it('keeps the versioned Xiam evaluation corpus partitioned and contract-valid', function (): void {
    $corpus = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/Ai/xiam-eval-cases.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($corpus['schema_version'])->toBe(2)
        ->and($corpus['dataset_version'])->toMatch('/^\d{4}-\d{2}-\d{2}\.\d+$/')
        ->and(array_keys($corpus['splits']))->toBe(['development', 'regression', 'heldout']);

    $knownStages = [
        'stage_1_setup',
        'stage_2_admission',
        'stage_3_exam_scheduling',
        'stage_4_examination_day',
        'stage_5_result_preparation',
        'stage_6_consultation',
        'stage_7_post_admission_handoff',
    ];
    $knownPagePacks = collect(app(PageCapabilityPackRegistry::class)->all())
        ->mapWithKeys(static fn ($pack): array => [$pack->key => $pack->version]);
    $caseIds = array_column($corpus['cases'], 'id');

    expect($caseIds)->toHaveCount(count(array_unique($caseIds)));

    $stageCoverage = [];
    $listedCaseIds = [];
    foreach ($corpus['splits'] as $split => $metadata) {
        expect($metadata['case_ids'])->not->toBeEmpty();

        foreach ($metadata['case_ids'] as $caseId) {
            expect($listedCaseIds)->not->toContain($caseId);
            $listedCaseIds[] = $caseId;
        }
    }

    foreach ($corpus['cases'] as $case) {
        expect($case)->toHaveKeys([
            'id',
            'split',
            'stage',
            'page_pack_key',
            'page_pack_version',
            'input_turns',
            'allowed_tool_ids',
            'required_tool_ids',
            'expected_domain_mutation',
            'critical',
        ])
            ->and($case['split'])->toBeIn(array_keys($corpus['splits']))
            ->and($corpus['splits'][$case['split']]['case_ids'])->toContain($case['id'])
            ->and($case['stage'])->toBeIn([...$knownStages, 'read'])
            ->and($case['input_turns'])->not->toBeEmpty()
            ->and($case['page_pack_key'])->toBeString()
            ->and($knownPagePacks->get($case['page_pack_key']))->toBe($case['page_pack_version']);

        if (in_array($case['stage'], $knownStages, true)) {
            $stageCoverage[$case['stage']] = true;
        }

        foreach (['allowed_tool_ids', 'required_tool_ids'] as $toolField) {
            expect($case[$toolField])->toBeArray();

            foreach ($case[$toolField] as $toolId) {
                expect($toolId)->toBeString()
                    ->and(preg_match('/^[a-z][a-z0-9_.-]*@\d+$/D', $toolId))->toBe(1);
            }
        }

        expect(array_diff($case['required_tool_ids'], $case['allowed_tool_ids']))->toBeEmpty();
        if (array_key_exists('forbidden_response_patterns', $case)) {
            $patterns = $case['forbidden_response_patterns'];

            expect($patterns)->toBeArray()
                ->and(array_is_list($patterns))->toBeTrue();

            foreach ($patterns as $pattern) {
                expect($pattern)->toBeString()
                    ->and(@preg_match($pattern, ''))->not->toBeFalse();
            }
        }
    }

    $unsupportedApplicantSurface = collect($corpus['coverage_metadata']['unsupported_surfaces'])
        ->first(static fn (array $surface): bool => $surface['stage'] === 'stage_2_admission'
            && $surface['surface'] === 'applicant_own_admission_status'
            && $surface['evaluated'] === false);

    expect($listedCaseIds)->toEqualCanonicalizing($caseIds)
        ->and(array_keys($stageCoverage))->toEqualCanonicalizing($knownStages)
        ->and($unsupportedApplicantSurface)->not->toBeNull();
});
