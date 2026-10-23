<?php

use App\Applicant\Support\ApplicantResultAccess;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\Program;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\ReleaseResult;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Queries\ReleaseOverviewQuery;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/ExternalFinalFixtures.php';
require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
    ]);
});

function individualPreparationHtmlTemplate(): ResultSheetTemplate
{
    return ResultSheetTemplate::query()->create([
        'name' => 'Individual Preparation Result Sheet',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
}

test('failed early preparation rolls back a new individual freeze and document pointer', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'prepare-failure');
    ResultSheetTemplate::query()->where('is_active', true)->update(['is_active' => false]);
    $attempt = $fixture['attempts']['complete'];
    $administration = $fixture['administrations']['complete'];
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $bindingState = $administration->fresh()->binding_state;

    expect(fn () => app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance))
        ->toThrow(RuntimeException::class, 'No active result sheet template.');

    expect(AssessmentResultSet::query()->where('administration_id', $administration->id)->count())->toBe(0)
        ->and($administration->fresh()->binding_state)->toBe($bindingState)
        ->and($attempt->fresh()->current_result_document_id)->toBeNull()
        ->and(ResultDocument::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and($fixture['grading_session']->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('preparation rejects a formerly scored but no longer active sitting without freezing it', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'prepare-stale-sitting');
    $attempt = $fixture['attempts']['complete'];
    $administration = $fixture['administrations']['complete'];
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $fixture['assignments']['complete']->update(['status' => ExamAssignment::STATUS_CANCELLED]);

    expect(fn () => app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance))
        ->toThrow(RuntimeException::class, 'Active exam assignment required');

    expect(AssessmentResultSet::query()->where('administration_id', $administration->id)->exists())->toBeFalse()
        ->and($attempt->fresh()->current_result_document_id)->toBeNull();
});

test('prepare freezes one exact applicant in an open batch and finalization reuses that snapshot', function (): void {
    Storage::fake('local');
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'prepare-early');
    $attempt = $fixture['attempts']['complete'];
    $incompleteAttempt = $fixture['attempts']['incomplete'];
    $administration = $fixture['administrations']['complete'];
    $grading = $fixture['grading_session'];
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    individualPreparationHtmlTemplate();

    expect(AttemptResultReadiness::isReady($attempt->fresh()))->toBeFalse();
    $prepared = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance);
    $replayed = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance);
    $resultSet = AssessmentResultSet::query()->where('administration_id', $administration->id)->sole();
    $frozenActorId = (int) $resultSet->finalized_by_user_id;
    $frozenAt = $resultSet->finalized_at->toISOString();
    $rawVector = $resultSet->raw_revision_vector_json;
    $conversionVector = $resultSet->conversion_revision_vector_json;
    $documentChecksum = $prepared->checksum;

    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN)
        ->and($prepared->id)->toBe($replayed->id)
        ->and($attempt->fresh()->current_result_document_id)->toBe($prepared->id)
        ->and(AttemptResultReadiness::isReady($attempt->fresh()))->toBeTrue()
        ->and($prepared->snapshot_payload_json['_assessment_result_set_id'])->toBe($resultSet->id)
        ->and($prepared->snapshot_payload_json['scores'][0]['area_code'])->toBe('RAW_A')
        ->and($prepared->snapshot_payload_json['scores'][0]['converted'])->toBe('82')
        ->and($prepared->snapshot_payload_json['scores'][0]['raw'])->toBe('')
        ->and($incompleteAttempt->fresh()->current_result_document_id)->toBeNull()
        ->and(AssessmentResultSet::query()->where('administration_id', $fixture['administrations']['incomplete']->id)->exists())->toBeFalse();

    $release = app(ReleaseResult::class)->handle(
        $attempt->fresh(),
        $prepared,
        $this->guidance,
        'early-individual-release-'.$attempt->id,
        ReleaseResult::MODE_ONLINE,
    );
    expect($release->is_current)->toBeTrue()
        ->and($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
    expect(fn () => app(RecordScores::class)->handle(
        gradingSession: $grading->fresh(),
        attempt: $attempt->fresh(),
        scores: [[
            'metric_code' => 'RAW_A',
            'typed_outputs' => ['percentile_rank' => '83', 'stanine' => '7'],
            'external_provenance' => 'Test-only external scoring source; no institutional result claim.',
        ]],
        actor: $this->guidance,
        idempotencyKey: 'post-prepare-mutation-'.$attempt->id,
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $fixture['kit_version']->id,
    ))->toThrow(RuntimeException::class);

    recordIndividualReleaseExternalFinal($fixture, 'incomplete', $this->guidance);
    app(FinalizeGradingSession::class)->handle($grading->fresh(), $this->guidance);

    $resultSet->refresh();
    $prepared->refresh();
    expect($grading->fresh()->status)->toBe(GradingSession::STATUS_FINALIZED)
        ->and((int) $resultSet->finalized_by_user_id)->toBe($frozenActorId)
        ->and($resultSet->finalized_at->toISOString())->toBe($frozenAt)
        ->and($resultSet->raw_revision_vector_json)->toBe($rawVector)
        ->and($resultSet->conversion_revision_vector_json)->toBe($conversionVector)
        ->and($prepared->checksum)->toBe($documentChecksum)
        ->and($attempt->fresh()->current_result_document_id)->toBe($prepared->id);
});

test('printing hub exposes a partly scored open batch and keeps preparation separate from printable backlog', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'printing-hub-open');
    $attempt = $fixture['attempts']['complete'];
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    individualPreparationHtmlTemplate();

    $this->actingAs($this->guidance)->get(route('release.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.grading_session_id', $fixture['grading_session']->id)
            ->where('sessions.0.status', GradingSession::STATUS_OPEN)
            ->where('sessions.0.examinees_count', 2)
            ->where('sessions.0.scored_count', 1)
            ->where('sessions.0.eligible_unprepared_count', 1)
            ->where('sessions.0.prepared_count', 0)
            ->where('sessions.0.unprinted_count', 0)
            ->where('sessions.0.print_backlog_count', 1));

    app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance);
    $this->actingAs($this->guidance)->get(route('release.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sessions.0.status', GradingSession::STATUS_OPEN)
            ->where('sessions.0.scored_count', 1)
            ->where('sessions.0.eligible_unprepared_count', 0)
            ->where('sessions.0.prepared_count', 1)
            ->where('sessions.0.unprinted_count', 1)
            ->where('sessions.0.print_backlog_count', 1));
});

test('printing overview applies its visible limit after skipping earlier unscored history windows', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'printing-history-window');
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    for ($index = 1; $index <= 26; $index++) {
        $session = $fixture['session']->replicate(['code']);
        $session->forceFill([
            'code' => 'SYN-UNSCORED-HISTORY-'.$index,
            'starts_at' => now()->subDays($index + 1),
            'effective_end_at' => now()->subDays($index + 1)->addHours(2),
            'closed_at' => now()->subDays($index + 1)->addHours(2),
        ])->save();
        GradingSession::query()->create([
            'exam_session_id' => $session->id,
            'status' => GradingSession::STATUS_OPEN,
            'opened_at' => now()->subDays($index),
            'opened_by_user_id' => $this->guidance->id,
            'version' => 1,
        ]);
    }

    $overview = app(ReleaseOverviewQuery::class)->forInertia(
        (int) $fixture['attempts']['complete']->institution_id, 1,
        ['status' => 'unprinted', 'sort' => 'exam_date', 'dir' => 'asc'],
    );
    expect(array_column($overview['sessions'], 'id'))->toBe([$fixture['grading_session']->id])
        ->and($overview['sessions'][0]['scored_count'])->toBe(1)
        ->and($overview['sessions'][0]['print_backlog_count'])->toBe(1);
});
test('result sheet preserves zero percentile rank and does not invent raw score or overall value for external final', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'sheet-zero-external-final');
    $attempt = $fixture['attempts']['complete'];
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance, percentile: '0');
    individualPreparationHtmlTemplate();

    $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $this->guidance);
    $resultSet = AssessmentResultSet::query()
        ->where('administration_id', $fixture['administrations']['complete']->id)
        ->sole();
    $data = app(ResultSheetDataProvider::class)->forAttempt($attempt->fresh(), $resultSet);
    $score = $data['scores'][0];
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);

    expect($score['raw'])->toBe('')
        ->and($score['converted'])->toBe('0')
        ->and($score['scale'])->toBe('percentile_rank')
        ->and($data['overall_pct'])->toBeNull()
        ->and($replacements['overall_pct'])->toBe('')
        ->and($replacements['score_table_raw_heading'])->toBe('')
        ->and($replacements['score_table_final_heading'])->toBe('Percentile rank')
        ->and($replacements['scores_rows'])->toContain('<td class="text-center">0</td>')
        ->and($document->snapshot_payload_json['scores'][0]['raw'])->toBe('')
        ->and($document->snapshot_payload_json['scores'][0]['converted'])->toBe('0');
});
test('pure final-only result renders zero PR without raw tags, source metadata, or an invented overall', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    app(RecordScores::class)->handle(
        gradingSession: $fixture['gradingSession'],
        attempt: $fixture['attempt'],
        scores: externalFinalScoreEntries($fixture['profile']),
        actor: $this->guidance,
        idempotencyKey: 'result-sheet-final-only-'.$fixture['attempt']->id,
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $fixture['kitVersion']->id,
    );
    individualPreparationHtmlTemplate();
    $document = app(PrepareResultDocument::class)->handle($fixture['attempt']->fresh(), $this->guidance);
    actingAsAdmissionSupportStaff($this, $this->guidance)->get(route('scoring.sessions.attempts.scores', [
        'examSession' => $fixture['gradingSession']->exam_session_id,
        'attempt' => $fixture['attempt']->id,
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('grading_status', GradingSession::STATUS_OPEN)
        ->where('finalized', false)
        ->where('individual_frozen', true)
        ->where('can_write', false));
    $release = app(ReleaseResult::class)->handle(
        $fixture['attempt']->fresh(),
        $document,
        $this->guidance,
        'result-sheet-final-only-release-'.$fixture['attempt']->id,
        ReleaseResult::MODE_ONLINE,
    );
    $access = app(ApplicantResultAccess::class)->forAttempt($fixture['attempt']->fresh(), verifyArtifact: false);
    $resultSet = AssessmentResultSet::query()
        ->where('administration_id', $fixture['administration']->id)
        ->sole();
    $data = app(ResultSheetDataProvider::class)->forAttempt($fixture['attempt']->fresh(), $resultSet);
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([$data]);
    $program = Program::query()->where('code', $fixture['attempt']->program_preference_1_code)->firstOrFail();
    $candidateLists = app(ProgramHeadCandidateListsQuery::class)->for(
        $program,
        $fixture['attempt']->academicCycle->code,
    );
    $candidate = collect($candidateLists['potential'])->firstWhere('attempt_id', $fixture['attempt']->id);

    expect($data['scores'])->toHaveCount(6)
        ->and($data['scores'][0]['converted'])->toBe('0.00')
        ->and($data['scores'][0]['scale'])->toBe('percentile_rank')
        ->and($data['scores'][0]['raw'])->toBe('')
        ->and($data['overall_pct'])->toBeNull()
        ->and($access['available'])->toBeTrue()
        ->and($access['document']->id)->toBe($document->id)
        ->and($release->result_document_id)->toBe($document->id)
        ->and($candidate['score'])->toBeNull()
        ->and($candidate['result_available'])->toBeTrue()
        ->and($replacements['score_table_raw_heading'])->toBe('')
        ->and($replacements['verbal_aptitude_pct'])->toBe('0.00')
        ->and($replacements['general_ability_pct'])->toBe('81.25')
        ->and($replacements['scores_rows'])->toContain('<td class="text-center">0.00</td>')
        ->and($document->snapshot_payload_json['scores'][0])->not->toHaveKey('source')
        ->and($document->snapshot_payload_json['scores'][0])->not->toHaveKey('external_provenance')
        ->and($document->snapshot_payload_json['scores'][0])->not->toHaveKey('result_set_id');
});
test('a frozen result set supplied for another applicant is never rendered into this applicant sheet', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'sheet-result-owner');
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance, percentile: '20');
    recordIndividualReleaseExternalFinal($fixture, 'incomplete', $this->guidance, percentile: '80');
    individualPreparationHtmlTemplate();
    app(PrepareResultDocument::class)->handle($fixture['attempts']['complete']->fresh(), $this->guidance);
    app(PrepareResultDocument::class)->handle($fixture['attempts']['incomplete']->fresh(), $this->guidance);

    $foreignResultSet = AssessmentResultSet::query()
        ->where('administration_id', $fixture['administrations']['incomplete']->id)
        ->sole();
    expect(fn () => app(ResultSheetDataProvider::class)->forAttempt(
        $fixture['attempts']['complete']->fresh(),
        $foreignResultSet,
    ))->toThrow(InvalidArgumentException::class);
});

test('printing applicant search narrows across open sessions without turning relationship keys into alternatives', function (): void {
    $first = individualReleaseReadinessFixture($this, $this->guidance, 'printing-search-first');
    $second = individualReleaseReadinessFixture($this, $this->guidance, 'printing-search-second');
    recordIndividualReleaseExternalFinal($first, 'complete', $this->guidance);
    recordIndividualReleaseExternalFinal($second, 'complete', $this->guidance);

    foreach (['complete', 'incomplete'] as $candidate) {
        $this->actingAs($this->guidance)->get(route('release.index', [
            'search' => $second['attempts'][$candidate]->reference_code,
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.exam_session_id', $second['session']->id)
            ->where('sessions.0.status', GradingSession::STATUS_OPEN));
    }
    $this->actingAs($this->guidance)->get(route('release.index', [
        'search' => 'NO-SUCH-SYNTHETIC-PRINT-APPLICANT',
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sessions', 0)
        ->where('filters.search', 'NO-SUCH-SYNTHETIC-PRINT-APPLICANT'));
});
