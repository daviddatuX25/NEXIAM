<?php

use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Tests\TestCase;

function finalizedScoredFixture(TestCase $test, int $count = 1): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $attempts = $fixture['attempts'];

    foreach ($attempts as $attempt) {
        $scores = collect(activeAreas())->map(fn (int $id): array => [
            'aptitude_area_id' => $id,
            'score' => 85,
        ])->all();

        $test->actingAs($guidance)
            ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
                'scores' => $scores,
                'idempotency_key' => 'k-provider-'.$attempt->id,
            ])
            ->assertRedirect();

        $test->actingAs($guidance)
            ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
            ->assertRedirect();
    }

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
        ->and($data['overall_pct'])->toBeInt()
        ->and($data['overall_pct'])->toBeGreaterThan(0);
});

test('scores carry the frozen converted rating and raw value', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $score = $data['scores'][0];

    expect($score['converted'])->toBe('Above Average')
        ->and($score['rating'])->toBe('Above Average')
        ->and($score['raw'])->toBe('85.00')
        ->and($score['pct'])->toBe(85);
});

test('placeholder service resolves legacy aliases and scores rows from real data', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    $content = '<p>{{applicant_name}} {{applicant_reference}}</p>'
        .'<p>{{verbal_aptitude}} {{verbal_aptitude_rating}} {{verbal_aptitude_raw}}</p>'
        .'<table><tbody class="scores-rows-placeholder"></tbody></table>'
        .'<p>{{overall_pct}}</p>';

    $html = app(ResultSheetPlaceholderService::class)->renderRaw($content, $replacements);

    expect($html)->toContain($data['name'])
        ->and($html)->toContain($attempt->reference_code)
        ->and($html)->toContain('Above Average')
        ->and($html)->toContain('85.00')
        ->and($html)->not->toContain('{{applicant_name}}')
        ->and($html)->not->toContain('{{applicant_reference}}')
        ->and($html)->not->toContain('{{verbal_aptitude}}')
        ->and($html)->not->toContain('{{verbal_aptitude_raw}}');
});

test('dual slot placeholders render the second applicant separately', function (): void {
    $fixture = finalizedScoredFixture($this, count: 2);
    $attempts = $fixture['attempts'];

    $provider = app(ResultSheetDataProvider::class);
    $data1 = $provider->forAttempt($attempts[0]);
    $data2 = $provider->forAttempt($attempts[1]);

    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data1, $data2]);

    expect($replacements['applicant_name'])->toBe($data1['name'])
        ->and($replacements['applicant_name_2'])->toBe($data2['name'])
        ->and($replacements['scores_rows_2'])->not->toBe('')
        ->and($replacements['overall_pct_2'])->not->toBe('');
});

test('course checkmark resolves for the recommended program', function (): void {
    $fixture = finalizedScoredFixture($this);
    $attempt = $fixture['attempts'][0];

    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt);
    $data['recommended_course_code'] = 'BSN';

    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    expect($replacements['BSN_check'])->toBe('✔');
});
