<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function applicantScoringProfile(User $actor): TestInstrumentVersion
{
    $instrument = TestInstrument::query()->create([
        'institution_id' => Institution::query()->firstOrFail()->id,
        'code' => 'APPLICANT-SCORING-'.Str::upper(Str::random(8)),
    ]);
    $timestamp = now();
    $versionId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'Applicant scoring test profile',
        'material_requirement' => 'no_paper',
        'administration_procedure_reference' => 'Applicant scoring test fixture',
        'lock_version' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $metricId = DB::table('test_instrument_metrics')->insertGetId([
        'test_instrument_version_id' => $versionId,
        'code' => 'RAW-A',
        'name' => 'Raw metric',
        'rule_kind' => 'sum_raw_metrics',
        'unit' => 'count',
        'precision_scale' => 0,
        'is_required' => true,
        'required_output_scales' => json_encode([]),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('test_instrument_versions')
        ->where('id', $versionId)
        ->update([
            'status' => TestInstrumentVersion::STATUS_APPROVED,
            'source_reviewed_by_user_id' => $actor->id,
            'source_reviewed_at' => $timestamp,
            'approved_by_user_id' => $actor->id,
            'approved_at' => $timestamp,
            'content_sha256' => hash('sha256', 'applicant-scoring-profile-'.$versionId),
            'updated_at' => $timestamp,
        ]);
    DB::table('test_instruments')
        ->where('id', $instrument->id)
        ->update(['current_approved_version_id' => $versionId, 'updated_at' => $timestamp]);

    return TestInstrumentVersion::query()->findOrFail($versionId);
}
function applicantScoringAccount(string $email): array
{
    seedE2Foundation();
    $fixture = submitSyntheticApplicant($email, 'Scoring Applicant');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($fixture['access_token'], '2001-01-01');

    return [$fixture['attempt'], $applicant];
}

test('exam scoring is own latest active sitting and projects current source-backed metadata without scores', function (): void {
    [$attempt, $applicant] = applicantScoringAccount('exam-scoring-own@example.test');
    $other = submitSyntheticApplicant('exam-scoring-foreign@example.test', 'Foreign')['attempt'];
    $scorer = User::factory()->create(['name' => 'Scoring Clerk']);
    $finalizer = User::factory()->create(['name' => 'Finalizing Officer']);

    $room = Room::query()->where('is_active', true)->firstOrFail();
    $oldSession = ExamSession::factory()->create(['room_id' => $room->id]);
    $oldAssignment = ExamAssignment::factory()->create([
        'exam_session_id' => $oldSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_REMOVED,
    ]);
    $currentSession = ExamSession::factory()->create(['room_id' => $room->id]);
    $assignment = ExamAssignment::factory()->create(['exam_session_id' => $currentSession->id, 'admission_attempt_id' => $attempt->id]);
    $foreignAssignment = ExamAssignment::factory()->create(['exam_session_id' => $currentSession->id, 'admission_attempt_id' => $other->id]);
    $profile = applicantScoringProfile($scorer);

    $oldAdministration = scoringAdministration($oldAssignment, $attempt, $profile, 1);
    $administration = scoringAdministration($assignment, $attempt, $profile, 1);
    scoringAdministration($foreignAssignment, $other, $profile, 1);

    $metricId = TestInstrumentMetric::query()->where('test_instrument_version_id', $profile->id)->firstOrFail()->id;
    $oldRun = AssessmentScoringRun::query()->create([
        'administration_id' => $oldAdministration->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => AssessmentScoringRun::SOURCE_MANUAL,
        'actor_user_id' => $finalizer->id,
        'idempotency_key' => 'applicant-scoring-old-run',
        'created_at' => now()->subDays(2),
    ]);
    AssessmentRawResultRevision::query()->create([
        'administration_id' => $oldAdministration->id,
        'scoring_run_id' => $oldRun->id,
        'metric_id' => $metricId,
        'metric_code' => 'OLD-METRIC',
        'revision_no' => 1,
        'raw_value' => 11.11,
        'source' => 'manual',
        'status' => 'valid',
        'provenance_json' => [],
        'is_current' => true,
    ]);
    $run = AssessmentScoringRun::query()->create([
        'administration_id' => $administration->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => AssessmentScoringRun::SOURCE_MANUAL,
        'actor_user_id' => $scorer->id,
        'idempotency_key' => 'applicant-scoring-current-run',
        'created_at' => now()->subHours(3),
    ]);
    AssessmentRawResultRevision::query()->create([
        'administration_id' => $administration->id,
        'scoring_run_id' => $run->id,
        'metric_id' => $metricId,
        'metric_code' => 'SAFE-METRIC',
        'revision_no' => 1,
        'raw_value' => 987.65,
        'source' => 'manual',
        'status' => 'valid',
        'provenance_json' => ['private_note' => 'must not leak'],
        'is_current' => true,
        'created_at' => now()->subHours(3),
    ]);
    $finalizedAt = now()->subHour();
    $gradingSession = GradingSession::query()->create([
        'exam_session_id' => $currentSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(1),
        'finalized_at' => $finalizedAt,
        'finalized_by_user_id' => $finalizer->id,
    ]);
    AssessmentResultSet::query()->create([
        'administration_id' => $administration->id,
        'grading_session_id' => $gradingSession->id,
        'raw_revision_vector_json' => ['SAFE-METRIC' => 1],
        'conversion_revision_vector_json' => [],
        'status' => AssessmentResultSet::STATUS_FINALIZED,
        'finalized_by_user_id' => $finalizer->id,
        'finalized_at' => $finalizedAt,
    ]);

    $response = $this->actingAs($applicant)->get(route('applicant.exam-scoring'));
    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Applicant/ExamScoring')
        ->where('page_state', 'ready')
        ->where('scoring.status', 'finalized')
        ->where('scoring.recordings.0.recorded_by', 'Scoring Clerk')
        ->where('scoring.recordings.0.recorded_at', $run->created_at->toIso8601String())
        ->where('scoring.finalization.finalized_by', 'Finalizing Officer')
        ->where('scoring.finalization.finalized_at', $finalizedAt->toIso8601String())
        ->missing('scoring.recordings.1.recorded_by')
        ->missing('scoring.recordings.0.raw_value')
        ->missing('scoring.recordings.0.provenance_json')
        ->missing('scoring.finalization.raw_revision_vector_json')
        ->missing('scoring.finalization.conversion_revision_vector_json')
        ->missing('scores'));

    expect($oldAdministration->id)->toBeLessThan($administration->id);
});

test('exam scoring does not invent attribution or finalization when current raw revisions lack actor', function (): void {
    [$attempt, $applicant] = applicantScoringAccount('exam-scoring-unattributed@example.test');
    $room = Room::query()->where('is_active', true)->firstOrFail();
    $session = ExamSession::factory()->create(['room_id' => $room->id]);
    $assignment = ExamAssignment::factory()->create(['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id]);
    $profile = applicantScoringProfile(User::factory()->create());
    $administration = scoringAdministration($assignment, $attempt, $profile, 1);
    $run = AssessmentScoringRun::query()->create([
        'administration_id' => $administration->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => AssessmentScoringRun::SOURCE_MANUAL,
        'actor_user_id' => null,
        'idempotency_key' => 'applicant-scoring-null-actor',
        'created_at' => now(),
    ]);
    AssessmentRawResultRevision::query()->create([
        'administration_id' => $administration->id,
        'scoring_run_id' => $run->id,
        'metric_id' => TestInstrumentMetric::query()->where('test_instrument_version_id', $profile->id)->firstOrFail()->id,
        'metric_code' => 'SAFE-METRIC',
        'revision_no' => 1,
        'raw_value' => 2,
        'source' => 'manual',
        'status' => 'valid',
        'provenance_json' => [],
        'is_current' => true,
    ]);

    $this->actingAs($applicant)->get(route('applicant.exam-scoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scoring.status', 'in_progress')
            ->where('scoring.recordings.0.recorded_by', null)
            ->where('scoring.finalization', null));
});

test('legacy scoring identifies each own score record and labels its update timestamp', function (): void {
    [$attempt, $applicant] = applicantScoringAccount('exam-scoring-legacy@example.test');
    $room = Room::query()->where('is_active', true)->firstOrFail();
    $session = ExamSession::factory()->create(['room_id' => $room->id]);
    $assignment = ExamAssignment::factory()->create(['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id]);
    $scorer = User::factory()->create(['name' => 'Legacy Scorer']);
    $secondScorer = User::factory()->create(['name' => 'Another Legacy Scorer']);
    $area = AptitudeArea::query()->firstOrFail();
    $gradingSession = GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now()->subDay(),
    ]);
    $score = ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $area->id,
        'score' => 'private-score-value',
        'converted_score' => 'private-converted-value',
        'recorded_by_user_id' => $scorer->id,
        'idempotency_key' => 'legacy-applicant-scoring-'.$assignment->id,
    ]);
    $otherAreas = AptitudeArea::query()->whereKeyNot($area->id)->orderBy('id')->limit(2)->get();
    $duplicateScore = ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $otherAreas[0]->id,
        'score' => 'another-private-score',
        'converted_score' => 'another-private-converted',
        'recorded_by_user_id' => $scorer->id,
        'idempotency_key' => 'legacy-applicant-scoring-duplicate-'.$assignment->id,
    ]);
    $otherActorScore = ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $otherAreas[1]->id,
        'score' => 'third-private-score',
        'converted_score' => 'third-private-converted',
        'recorded_by_user_id' => $secondScorer->id,
        'idempotency_key' => 'legacy-applicant-scoring-other-actor-'.$assignment->id,
    ]);
    $recordedAt = now()->subHours(2);
    DB::table('score_records')
        ->whereIn('id', [$score->id, $duplicateScore->id, $otherActorScore->id])
        ->update(['updated_at' => $recordedAt]);

    $this->actingAs($applicant)->get(route('applicant.exam-scoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scoring.status', 'in_progress')
            ->where('scoring.recordings.0.recorded_by', 'Legacy Scorer')
            ->where('scoring.recordings.0.recorded_at', $recordedAt->toIso8601String())
            ->where('scoring.recordings.1.recorded_by', 'Another Legacy Scorer')
            ->where('scoring.recordings.1.recorded_at', $recordedAt->toIso8601String())
            ->missing('scoring.recordings.2.recorded_by')
            ->missing('scoring.recordings.0.score')
            ->missing('scoring.recordings.0.converted_score'));
});

function scoringAdministration(ExamAssignment $assignment, AdmissionAttempt $attempt, TestInstrumentVersion $profile, int $revision): AssessmentAdministration
{
    return AssessmentAdministration::query()->create([
        'exam_assignment_id' => $assignment->id,
        'admission_attempt_id' => $attempt->id,
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'binding_revision' => $revision,
        'test_instrument_version_id' => $profile->id,
        'required_metric_set' => [],
        'binding_state' => AssessmentAdministration::STATE_READY,
        'bound_at' => now(),
    ]);
}
