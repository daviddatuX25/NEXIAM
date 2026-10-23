<?php

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Database\Seeders\FoundationalScoringSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

function finalizedScoredFixture(TestCase $test, int $count = 1): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempts = $fixture['attempts'];

    foreach ($attempts as $attempt) {
        foreach (activeAreas() as $areaId) {
            $area = AptitudeArea::query()->findOrFail($areaId);
            ScoreRecord::query()->create([
                'grading_session_id' => $grading->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $areaId,
                'score' => number_format(min(20, (int) $area->max_items), 2, '.', ''),
                'converted_score' => 'Above Average',
                'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id,
                'idempotency_key' => 'k-provider-'.$attempt->id.'-'.$areaId,
            ]);
        }
    }

    $grading->update(['status' => GradingSession::STATUS_FINALIZED]);

    return $fixture;
}

test('data provider maps a scored attempt to applicant data with frozen ratings', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);

    expect($data['reference'])->toBe($attempt->reference_code)
        ->and($data['name'])->not->toBe('')
        ->and($data['family_name'])->not->toBe('')
        ->and($data['scores'])->not->toBeEmpty()
        ->and($data['overall_pct'])->toBeNull();
});

test('data provider exposes raw school and computed short values without history fallback', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];
    $attempt->update([
        'last_school_enrolled' => 'Ilocos Sur College',
        'strand' => null,
        'applicant_type' => 'transferee',
    ]);

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt->fresh());

    expect($data['last_school_enrolled'])->toBe('Ilocos Sur College')
        ->and($data['last_school_short'])->toBe('ISC')
        ->and($data['strand_or_prev_course_short'])->toBe('')
        ->and($data['strand_or_prev_course'])->toBe('Ilocos Sur College');
});

test('scores carry the frozen converted rating and raw value', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];
    $area = AptitudeArea::query()->findOrFail(activeAreas()[0]);
    $rawScore = min(20, (int) $area->max_items);

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $score = $data['scores'][0];

    expect($score['converted'])->toBe('Above Average')
        ->and($score['rating'])->toBe('Above Average')
        ->and($score['raw'])->toBe(number_format($rawScore, 2, '.', ''))
        ->and($score['pct'])->toBe($area->max_items > 0 ? (int) round(($rawScore / $area->max_items) * 100) : 0);
});

test('placeholder service resolves legacy aliases and scores rows from real data', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    $content = '<p>{{applicant_name}} {{applicant_reference}}</p>'
        .'<p>{{general_ability}} {{verbal_aptitude}} {{numerical_aptitude}} {{spatial_aptitude}} {{perceptual_aptitude}} {{manual_dexterity}}</p>'
        .'<p>{{verbal_aptitude_rating}} {{verbal_aptitude_raw}}</p>'
        .'<table><tbody class="scores-rows-placeholder"></tbody></table>'
        .'<p>{{overall_pct}}</p>';

    $html = app(ResultSheetPlaceholderService::class)->renderRaw($content, $replacements);

    expect($html)->toContain($data['name'])
        ->and($html)->toContain($attempt->reference_code)
        ->and($html)->toContain('Above Average')
        ->and($html)->toContain(number_format(min(20, (int) AptitudeArea::query()->findOrFail(activeAreas()[0])->max_items), 2, '.', ''))
        ->and($html)->not->toContain('{{applicant_name}}')
        ->and($html)->not->toContain('{{applicant_reference}}')
        ->and($html)->not->toContain('{{general_ability}}')
        ->and($html)->not->toContain('{{verbal_aptitude}}')
        ->and($html)->not->toContain('{{numerical_aptitude}}')
        ->and($html)->not->toContain('{{spatial_aptitude}}')
        ->and($html)->not->toContain('{{perceptual_aptitude}}')
        ->and($html)->not->toContain('{{manual_dexterity}}')
        ->and($html)->not->toContain('{{verbal_aptitude_raw}}')
        ->and($replacements['general_ability'])->not->toBe('')
        ->and($replacements['verbal_aptitude'])->not->toBe('')
        ->and($replacements['numerical_aptitude'])->not->toBe('')
        ->and($replacements['spatial_aptitude'])->not->toBe('')
        ->and($replacements['perceptual_aptitude'])->not->toBe('')
        ->and($replacements['manual_dexterity'])->not->toBe('');
});

test('course checkmark resolves for the recommended program', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $data['recommended_course_code'] = 'BSN';

    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    expect($replacements['BSN_check'])->toBe('✔');
});

test('OASIS 4-subtest manual scoring populates all 6 ICAT aptitude placeholders on the result sheet', function (): void {
    $this->seed(FoundationalScoringSeeder::class);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $kit = TestKit::query()->where('code', 'TK-ICAT-2026')->firstOrFail();
    $preparation = $kit->preparations()->where('state', 'preparing')->firstOrFail();
    $normId = (int) $preparation->norm_package_version_id;
    $version = app(AssembleTestKitPreparation::class)->handle($guidance, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $guidance->id,
        'revision' => (int) $version->revision + 1,
    ])->save();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $attempt = AdmissionAttempt::query()
        ->where('academic_cycle_id', $cycle->id)
        ->where('reference_code', 'APP-ICAT-2026-MIKA')
        ->firstOrFail();
    $created = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: [$attempt->id],
        actor: $guidance,
        label: 'ICAT 6-aptitude result sheet test',
        kitVersion: $version,
    );
    $session = $created['session'];
    $grading = $session->gradingSession;

    app(RecordScores::class)->handle(
        gradingSession: $grading,
        attempt: $attempt,
        scores: [
            ['metric_code' => 'VOCABULARY_RAW', 'score' => 34],
            ['metric_code' => 'COMPUTATION_RAW', 'score' => 28],
            ['metric_code' => 'SPATIAL_RAW', 'score' => 16],
            ['metric_code' => 'WORD_COMP_RAW', 'score' => 91],
        ],
        actor: $guidance,
        idempotencyKey: (string) Str::uuid(),
        scoringMode: 'manual_raw',
        testKitVersionId: (int) $version->id,
        normPackageVersionId: $normId,
    );

    app(FinalizeGradingSession::class)->handle(
        $grading->fresh(),
        $guidance,
        (int) $grading->fresh()->version,
        (string) Str::uuid(),
    );

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt->fresh());
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    expect($replacements['general_ability'])->not->toBe('')
        ->and($replacements['verbal_aptitude'])->not->toBe('')
        ->and($replacements['numerical_aptitude'])->not->toBe('')
        ->and($replacements['spatial_aptitude'])->not->toBe('')
        ->and($replacements['perceptual_aptitude'])->not->toBe('')
        ->and($replacements['manual_dexterity'])->not->toBe('')
        ->and($replacements['general_ability_raw'])->toBe('62.00')
        ->and($replacements['manual_dexterity_raw'])->toBe('107.00');
});
