<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Reports\Queries\PassingReportDataQuery;
use App\Reports\Surfaces\PassingReportSurface;
use Tests\TestCase;

function reportScoreRows(GradingSession $grading, AdmissionAttempt $attempt, array $scores): void
{
    foreach (array_values(activeAreas()) as $index => $areaId) {
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $areaId,
            'score' => $scores[$index] ?? $scores[0],
            'idempotency_key' => 'passing-report-'.$grading->id.'-'.$attempt->id.'-'.$areaId,
        ]);
    }
}

/** @return array<string, mixed> */
function finalizedReportFixture(TestCase $test, int $count = 3): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $fixture['grading']->update(['status' => GradingSession::STATUS_FINALIZED]);

    return $fixture;
}

test('passing report calculates examinees, passers, and percentage from finalized scores', function (): void {
    $fixture = finalizedReportFixture($this);
    reportScoreRows($fixture['grading'], $fixture['attempts'][0], [90]);
    reportScoreRows($fixture['grading'], $fixture['attempts'][1], [70]);
    reportScoreRows($fixture['grading'], $fixture['attempts'][2], [80]);

    $report = app(PassingReportDataQuery::class)->handle([
        'academic_cycle_id' => $fixture['session']->academic_cycle_id,
        'cutoff_score' => 75,
    ]);

    expect($report['total_examinees'])->toBe(3)
        ->and($report['total_passed'])->toBe(2)
        ->and($report['passing_rate'])->toBe('66.67%')
        ->and(array_column($report['passers'], 'composite_score'))->toBe([90.0, 80.0]);
});

test('passing report is strictly scoped to the selected academic cycle', function (): void {
    $fixture = finalizedReportFixture($this, 1);
    reportScoreRows($fixture['grading'], $fixture['attempts'][0], [91]);

    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $fixture['attempts'][0]->institution_id,
        'code' => 'PASSING-OTHER-CYCLE',
        'name' => 'Other Cycle',
        'opens_on' => '2027-01-01',
        'closes_on' => '2027-12-31',
    ]);
    $foreignExam = ExamSession::factory()->create([
        'academic_cycle_id' => $foreignCycle->id,
        'code' => 'EX-PASSING-OTHER',
    ]);
    $foreignGrading = GradingSession::query()->create([
        'exam_session_id' => $foreignExam->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'version' => 1,
        'opened_at' => now(),
    ]);
    reportScoreRows($foreignGrading, $fixture['attempts'][0], [99]);

    $report = app(PassingReportDataQuery::class)->handle([
        'academic_cycle_id' => $fixture['session']->academic_cycle_id,
    ]);

    expect($report['total_examinees'])->toBe(1)
        ->and($report['passers'][0]['composite_score'])->toBe(91.0);
});

test('passing report sorts equal composite scores by applicant last name', function (): void {
    $fixture = finalizedReportFixture($this, 3);
    $attempts = $fixture['attempts'];
    $attempts[0]->identity->update(['last_name' => 'Zulu']);
    $attempts[1]->identity->update(['last_name' => 'Adams']);
    $attempts[2]->identity->update(['last_name' => 'Baker']);
    foreach ($attempts as $attempt) {
        reportScoreRows($fixture['grading'], $attempt, [82]);
    }

    $report = app(PassingReportDataQuery::class)->handle([
        'academic_cycle_id' => $fixture['session']->academic_cycle_id,
    ]);

    expect(array_column($report['passers'], 'applicant_name'))->toBe(['Test Adams', 'Test Baker', 'Test Zulu']);
});

test('passing report returns unavailable raw-only statistics when no examinees have finalized scores', function (): void {
    $fixture = finalizedReportFixture($this, 1);

    $report = app(PassingReportDataQuery::class)->handle([
        'academic_cycle_id' => $fixture['session']->academic_cycle_id,
    ]);

    expect($report['total_examinees'])->toBe(0)
        ->and($report['total_passed'])->toBe(0)
        ->and($report['passing_rate'])->toBe('N/A')
        ->and($report['passers'])->toBe([]);
});

test('passing report formula-escapes applicant text fields', function (): void {
    $fixture = finalizedReportFixture($this, 4);
    $dangerousValues = ['=1+1', '+formula', '-formula', '@formula'];
    foreach ($fixture['attempts'] as $index => $attempt) {
        $dangerousValue = $dangerousValues[$index];
        $attempt->identity->update([
            'first_name' => $dangerousValue,
            'last_name' => 'Applicant'.$index,
            'sex' => $dangerousValue,
        ]);
        $attempt->update([
            'reference_code' => $dangerousValue.'REF',
            'strand' => $dangerousValue.'STRAND',
            'preferred_program_code' => $dangerousValue.'PROGRAM',
        ]);
        reportScoreRows($fixture['grading'], $attempt, [90 - $index]);
    }

    $report = app(PassingReportDataQuery::class)->handle([
        'academic_cycle_id' => $fixture['session']->academic_cycle_id,
    ]);
    $rowsByReference = collect($report['passers'])->keyBy('reference_code');

    foreach ($dangerousValues as $index => $dangerousValue) {
        $row = $rowsByReference->get("'".$dangerousValue.'REF');
        expect($row)->not->toBeNull()
            ->and($row['applicant_name'])->toStartWith("'".$dangerousValue)
            ->and($row['sex'])->toBe("'".$dangerousValue)
            ->and($row['strand'])->toBe("'".$dangerousValue.'STRAND')
            ->and($row['program_code'])->toBe("'".$dangerousValue.'PROGRAM');
    }
});

test('passing report surface resolves the same top-level keys as its sample catalog', function (): void {
    $fixture = finalizedReportFixture($this, 1);
    reportScoreRows($fixture['grading'], $fixture['attempts'][0], [88]);
    $surface = app(PassingReportSurface::class);
    $report = $surface->resolveData(['academic_cycle_id' => $fixture['session']->academic_cycle_id]);

    expect(array_keys($report))->toBe(array_keys($surface->sampleContext()))
        ->and($report['passers'][0])->toHaveKeys(['row_no', 'reference_code', 'applicant_name', 'sex', 'strand', 'program_code', 'composite_score', 'stanine', 'rating', 'remarks']);
});
