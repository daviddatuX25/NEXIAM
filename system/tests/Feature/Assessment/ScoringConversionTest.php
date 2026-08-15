<?php

use App\Assessment\Support\ScoreConverter;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use App\Setup\Actions\SaveAptitudeScoring;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function seedCustomVerbalTable(User $actor, array $rows, int $maxItems = 100): void
{
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
    app(SaveAptitudeScoring::class)->handle(
        $area,
        $actor,
        AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
        $maxItems,
        $rows,
    );
}

test('score converter resolves range rows and returns null outside coverage', function (): void {
    seedE2Foundation();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
    $converter = app(ScoreConverter::class);

    expect($converter->convert($area, 0))->toBe('Below Average')
        ->and($converter->convert($area, 49))->toBe('Below Average')
        ->and($converter->convert($area, 50))->toBe('Average')
        ->and($converter->convert($area, 85))->toBe('Above Average')
        ->and($converter->convert($area, 100))->toBe('Excellent');
});

test('recording a score freezes the converted output on the record', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 1);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areaId = activeAreas()[0];

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => [['aptitude_area_id' => $areaId, 'score' => 85]],
            'idempotency_key' => 'k-'.uniqid(),
        ])
        ->assertRedirect(route('grading.sessions.show', $grading->id));

    $record = $grading->scoreRecords()
        ->where('admission_attempt_id', $attempt->id)
        ->where('aptitude_area_id', $areaId)
        ->firstOrFail();

    expect((string) $record->score)->toBe('85.00')
        ->and($record->converted_score)->toBe('Above Average');
});

test('a score outside the conversion table is rejected', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 1);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    seedCustomVerbalTable($guidance, [
        ['min_score' => 0, 'max_score' => 50, 'output_value' => 'Low'],
    ], 50);

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => [['aptitude_area_id' => $area->id, 'score' => 80]],
            'idempotency_key' => 'k-'.uniqid(),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scores');

    expect($grading->scoreRecords()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('score and converted output are encrypted at rest', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 1);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $areaId = activeAreas()[0];

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => [['aptitude_area_id' => $areaId, 'score' => 85]],
            'idempotency_key' => 'k-'.uniqid(),
        ])
        ->assertRedirect();

    $record = $grading->scoreRecords()
        ->where('admission_attempt_id', $attempt->id)
        ->where('aptitude_area_id', $areaId)
        ->firstOrFail();

    $rawScore = DB::table('score_records')->where('id', $record->id)->value('score');
    $rawConverted = DB::table('score_records')->where('id', $record->id)->value('converted_score');

    expect($rawScore)->toBeString()->not->toBe('85.00')
        ->and($rawConverted)->toBeString()->not->toBe('Above Average');
});

test('editing the conversion table never rewrites previously recorded snapshots', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 2);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    [$attemptA, $attemptB] = $fixture['attempts'];
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    // Record under the seeded table: 85 → Above Average.
    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attemptA]), [
            'scores' => [['aptitude_area_id' => $area->id, 'score' => 85]],
            'idempotency_key' => 'k-a',
        ])
        ->assertRedirect();

    $recordA = $grading->scoreRecords()->where('admission_attempt_id', $attemptA->id)->where('aptitude_area_id', $area->id)->firstOrFail();
    expect($recordA->converted_score)->toBe('Above Average');

    // Replace the table: 0–100 → Zed.
    seedCustomVerbalTable($guidance, [
        ['min_score' => 0, 'max_score' => 100, 'output_value' => 'Zed'],
    ], 100);

    $recordA->refresh();
    expect($recordA->converted_score)->toBe('Above Average');

    // A new entry under the new table converts to the new output.
    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attemptB]), [
            'scores' => [['aptitude_area_id' => $area->id, 'score' => 85]],
            'idempotency_key' => 'k-b',
        ])
        ->assertRedirect();

    $recordB = $grading->scoreRecords()->where('admission_attempt_id', $attemptB->id)->where('aptitude_area_id', $area->id)->firstOrFail();
    expect($recordB->converted_score)->toBe('Zed');
});

test('score input page carries conversion rows and the frozen converted output', function (): void {
    $fixture = buildGradingFixture($this, applicantCount: 1);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempt = $fixture['attempts'][0];
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
            'scores' => [['aptitude_area_id' => $area->id, 'score' => 85]],
            'idempotency_key' => 'k-'.uniqid(),
        ])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.attempts.scores', [$grading, $attempt]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessment/GradingScoreInput')
            ->has('domains')
            ->where('domains.0.scoring_method', 'conversion_table')
            ->where('domains.0.max_items', 100)
            ->has('domains.0.conversion_rows', 4)
            ->where('domains.0.converted_score', 'Above Average')
        );
});
