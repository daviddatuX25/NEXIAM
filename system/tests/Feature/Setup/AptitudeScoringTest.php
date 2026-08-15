<?php

use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function scoringRows(int $start, int $end, string $out = 'Band'): array
{
    return [[
        'min_score' => $start,
        'max_score' => $end,
        'output_value' => $out,
    ]];
}

test('guidance counselor can open the scoring page for an aptitude area', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.aptitude.scoring', $area))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AptitudeScoring')
            ->where('area.code', 'VERBAL')
            ->where('area.scoring_method', 'conversion_table')
            ->where('area.max_items', 100)
            ->has('conversion_rows', 4)
        );
});

test('non-assessment staff cannot open the scoring page', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.aptitude.scoring', $area))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => scoringRows(0, 100),
        ])
        ->assertForbidden();
});

test('saving a full conversion table replaces the rows transactionally', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 50,
            'conversion_rows' => [
                ['min_score' => 0, 'max_score' => 24, 'output_value' => 'Low'],
                ['min_score' => 25, 'max_score' => 50, 'output_value' => 'High'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Scoring configuration saved.');

    $area->refresh();
    expect($area->max_items)->toBe(50)
        ->and($area->conversionRows()->count())->toBe(2)
        ->and($area->convertScore(10)?->output_value)->toBe('Low')
        ->and($area->convertScore(40)?->output_value)->toBe('High');
});

test('conversion table with a gap is rejected', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 0, 'max_score' => 49, 'output_value' => 'A'],
                ['min_score' => 51, 'max_score' => 100, 'output_value' => 'B'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scoring');
});

test('conversion table with overlapping ranges is rejected', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 0, 'max_score' => 60, 'output_value' => 'A'],
                ['min_score' => 40, 'max_score' => 100, 'output_value' => 'B'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scoring');
});

test('conversion table not covering the maximum score is rejected', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 0, 'max_score' => 99, 'output_value' => 'A'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scoring');
});

test('conversion table starting above zero is rejected', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 1, 'max_score' => 100, 'output_value' => 'A'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scoring');
});

test('request validation rejects malformed rows before the action runs', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'formula',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 5, 'max_score' => 1, 'output_value' => ''],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['scoring_method', 'conversion_rows.0.max_score', 'conversion_rows.0.output_value']);
});

test('failed saves keep the previous conversion table intact', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
    $before = $area->conversionRows()->count();

    $this->actingAs($guidance)
        ->put(route('setup.aptitude.scoring.update', $area), [
            'scoring_method' => 'conversion_table',
            'max_items' => 100,
            'conversion_rows' => [
                ['min_score' => 0, 'max_score' => 30, 'output_value' => 'A'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scoring');

    $area->refresh();
    expect($area->conversionRows()->count())->toBe($before)
        ->and($area->max_items)->toBe(100);
});
