<?php

use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\ScoreRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
});

test('final-only recording preserves six distinct sources and zero without raw or norm fiction and replays safely', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    $entries = externalFinalScoreEntries($fixture['profile']);
    $record = fn (array $scores, string $key): array => app(RecordScores::class)->handle(
        $fixture['gradingSession'], $fixture['attempt'], $scores, $this->guidance, $key,
        scoringMode: 'manual_converted', testKitVersionId: (int) $fixture['kitVersion']->id,
    );
    $record($entries, 'final-only-first');
    $revision = AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->current()->firstOrFail();
    expect($fixture['administration']->procedure_snapshot_json['operation'])->toBe('external_final')
        ->and($fixture['administration']->acceptance_receipt_id)->toBeNull()
        ->and($revision->typed_outputs_json['FINAL_1']['percentile_rank'])->toBe('0.00')
        ->and($revision->norm_package_version_id)->toBeNull()
        ->and($revision->provenance_json['acquisition_channel'])->toBe(ScoreRecord::SOURCE_MANUAL)
        ->and($revision->provenance_json['external_provenance'])->toBe(array_column($entries, 'external_provenance', 'metric_code'))
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->exists())->toBeFalse();
    $administration = $fixture['administration']->fresh()->load('testInstrumentVersion.metrics.construct', 'examAssignment.examSession.gradingSession');
    $ready = app(AssessmentAdministrationFinalizationPredicate::class)->evaluateAdministrationIndividual(
        $administration, collect(), collect(), $revision->load('rawDependencies'),
    );
    expect($ready['ok'])->toBeTrue();
    $record($entries, 'final-only-first');
    expect(AssessmentConversionRevision::query()->where('administration_id', $administration->id)->count())->toBe(1);
    $changed = $entries;
    $changed[0]['typed_outputs']['percentile_rank'] = '5.00';
    expect(fn () => $record($changed, 'final-only-first'))->toThrow(RuntimeException::class, 'idempotency_conflict');
    actingAsAdmissionSupportStaff($this, $this->guidance)->get(route('scoring.sessions.attempts.scores', [
        'examSession' => $fixture['gradingSession']->exam_session_id, 'attempt' => $fixture['attempt']->id,
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('is_final_only', true)
        ->where('scoring_mode', 'manual_converted')
        ->has('norm_versions', 0)
        ->where('domains.0.typed_outputs.percentile_rank', '0.00')
        ->where('domains.0.external_provenance', $entries[0]['external_provenance'])
        ->where('domains.1.external_provenance', $entries[1]['external_provenance']));
    actingAsAdmissionSupportStaff($this, $this->guidance)->get(route('scoring.sessions.show', [
        'examSession' => $fixture['gradingSession']->exam_session_id,
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('is_final_only', true)
        ->where('aptitude_areas_count', 6)
        ->where('progress_stats.scored', 1)
        ->where('applicants.0.domains_complete', 6)
        ->where('applicants.0.domains_total', 6));
    app(ClearScores::class)->handle($fixture['gradingSession'], $fixture['attempt'], $this->guidance);
    $cleared = $administration->fresh()->load('testInstrumentVersion.metrics.construct', 'examAssignment.examSession.gradingSession');
    $notReady = app(AssessmentAdministrationFinalizationPredicate::class)->evaluateAdministrationIndividual(
        $cleared, collect(), collect(), null,
    );
    expect($notReady['ok'])->toBeFalse()
        ->and($notReady['reason'])->toContain('final aptitude scores')
        ->and(AssessmentConversionRevision::query()->where('administration_id', $administration->id)->current()->exists())->toBeFalse();
    $record($entries, 'final-only-after-clear');
    app(FinalizeGradingSession::class)->handle($fixture['gradingSession'], $this->guidance);
    expect($fixture['gradingSession']->fresh()->status)->toBe('finalized');
    expect(fn () => app(ClearScores::class)->handle($fixture['gradingSession'], $fixture['attempt'], $this->guidance))
        ->toThrow(RuntimeException::class, 'finalized');
});

test('final-only recording defaults missing source to manual entry and rejects excess precision or missing aptitude', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    $entries = externalFinalScoreEntries($fixture['profile']);
    $record = fn (array $scores, string $mode = 'manual_converted') => app(RecordScores::class)->handle(
        $fixture['gradingSession'], $fixture['attempt'], $scores, $this->guidance, (string) Str::uuid(),
        scoringMode: $mode, testKitVersionId: (int) $fixture['kitVersion']->id,
    );
    $omittedSource = $entries;
    unset($omittedSource[2]['external_provenance']);
    $record($omittedSource);
    $revision = AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->current()->firstOrFail();
    expect($revision->provenance_json['external_provenance'][$fixture['profile']->metrics[2]->code])->toBe('Manual entry');
    app(ClearScores::class)->handle($fixture['gradingSession'], $fixture['attempt'], $this->guidance);
    $excessPrecision = $entries;
    $excessPrecision[1]['typed_outputs']['percentile_rank'] = '25.123';
    expect(fn () => $record($excessPrecision))->toThrow(ValidationException::class)
        ->and(fn () => $record(array_slice($entries, 0, 5)))->toThrow(ValidationException::class)
        ->and(fn () => $record($entries, 'manual_raw'))->toThrow(ValidationException::class);
    expect(AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->exists())->toBeFalse();
    $this->guidance->syncRoles([]);
    $this->guidance->load('permissions');
    User::query()->findOrFail($this->guidance->id)->revokePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL);
    expect(fn () => $record($entries))->toThrow(AuthorizationException::class);
});
