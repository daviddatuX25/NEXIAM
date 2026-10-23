<?php

use App\Models\User;
use App\Models\Walkthrough\WalkthroughProgress;
use App\Walkthrough\Queries\WalkthroughProgressProjection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

function createWalkthroughProgressApplicant(TestCase $test, string $email): User
{
    [, $applicant] = buildMessagingReadyAttempt($test, $email);

    return $applicant;
}

function postWalkthroughProgress(TestCase $test, User $applicant, array $payload): TestResponse
{
    return $test->actingAs($applicant)
        ->withSession(['auth.surface' => 'applicant'])
        ->postJson(route('walkthrough.progress.store'), $payload);
}

test('applicant walkthrough progress resumes and terminal dispositions cannot be undone by stale cursors', function (): void {
    $applicant = createWalkthroughProgressApplicant($this, 'walkthrough-progress-owner@example.test');
    $scope = app(WalkthroughProgressProjection::class)->scopeFor($applicant, 'applicant');
    $payload = [
        'scope_key' => $scope['key'],
        'page_key' => 'page:Applicant/ReleaseDay',
        'revision' => 1,
        'cursor' => 'release-outcome',
        'disposition' => 'in_progress',
    ];

    postWalkthroughProgress($this, $applicant, $payload)
        ->assertOk()
        ->assertJsonPath('record.disposition', 'in_progress')
        ->assertJsonPath('record.cursor', 'release-outcome');

    postWalkthroughProgress($this, $applicant, [
        ...$payload,
        'cursor' => 'current-instructions',
    ])->assertOk();

    postWalkthroughProgress($this, $applicant, [
        ...$payload,
        'cursor' => 'complete',
        'disposition' => 'completed',
    ])->assertOk()
        ->assertJsonPath('record.disposition', 'completed')
        ->assertJsonPath('record.cursor', 'complete');

    postWalkthroughProgress($this, $applicant, $payload)
        ->assertOk()
        ->assertJsonPath('record.disposition', 'completed')
        ->assertJsonPath('record.cursor', 'complete');

    expect(WalkthroughProgress::query()
        ->where('user_id', $applicant->getKey())
        ->where('page_key', 'page:Applicant/ReleaseDay')
        ->where('revision', 1)
        ->value('disposition'))->toBe('completed');
});

test('a stale account scope key is rejected and walkthrough rows remain account scoped', function (): void {
    $owner = createWalkthroughProgressApplicant($this, 'walkthrough-progress-scope-owner@example.test');
    $other = createWalkthroughProgressApplicant($this, 'walkthrough-progress-scope-other@example.test');
    $projection = app(WalkthroughProgressProjection::class);
    $ownerScope = $projection->scopeFor($owner, 'applicant');
    $otherScope = $projection->scopeFor($other, 'applicant');
    $payload = [
        'scope_key' => $ownerScope['key'],
        'page_key' => 'applicant-portal',
        'revision' => 4,
        'cursor' => 'milestones',
        'disposition' => 'in_progress',
    ];

    postWalkthroughProgress($this, $other, $payload)->assertStatus(409);
    expect(WalkthroughProgress::query()->where('user_id', $other->getKey())->exists())->toBeFalse();

    postWalkthroughProgress($this, $owner, [
        ...$payload,
        'disposition' => 'skipped',
    ])->assertOk();

    $ownerRecords = $projection->for($owner, 'applicant')['records'];
    expect($ownerRecords)->toHaveCount(1);
    expect($ownerRecords[0])->toMatchArray([
        'pageKey' => 'applicant-portal',
        'revision' => 4,
        'disposition' => 'skipped',
    ]);
    expect($projection->for($other, 'applicant')['records'])->toBe([]);
    expect($ownerScope['key'])->not->toBe($otherScope['key']);
});

test('walkthrough progress rejects non-semantic cursors and unsupported page keys', function (): void {
    $applicant = createWalkthroughProgressApplicant($this, 'walkthrough-progress-validation@example.test');
    $scope = app(WalkthroughProgressProjection::class)->scopeFor($applicant, 'applicant');

    postWalkthroughProgress($this, $applicant, [
        'scope_key' => $scope['key'],
        'page_key' => 'page:Applicant/ReleaseDay',
        'revision' => 1,
        'cursor' => 'AY26-00001',
        'disposition' => 'in_progress',
    ])->assertUnprocessable();

    postWalkthroughProgress($this, $applicant, [
        'scope_key' => $scope['key'],
        'page_key' => 'page:Applicant/AY26-00001',
        'revision' => 1,
        'cursor' => 'release-outcome',
        'disposition' => 'in_progress',
    ])->assertUnprocessable();
});
