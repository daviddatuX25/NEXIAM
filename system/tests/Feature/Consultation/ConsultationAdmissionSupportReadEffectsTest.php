<?php

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @return array{
 *     attempt: AdmissionAttempt,
 *     counselor: User,
 *     institution: Institution,
 *     cycle: AcademicCycle,
 *     activation: AdmissionBenchmarkActivation
 * }
 */
function consultationAdmissionSupportReadEffectsFixture(string $suffix): array
{
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()
        ->where('code', 'SYN-CYCLE-2026')
        ->where('is_active', true)
        ->firstOrFail();

    (new AdmissionBenchmarkSeeder)->run();
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    if (! $activation instanceof AdmissionBenchmarkActivation) {
        throw new RuntimeException('An active synthetic benchmark is required for this consultation fixture.');
    }

    $counselor = User::factory()->create([
        'name' => 'Guidance Counselor '.$suffix,
        'email' => 'consultation-read-effects-'.$suffix.'@example.test',
    ]);
    $counselor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'department_id' => null,
        'program_codes' => [],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $counselor->givePermissionTo(CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE);
    StaffAuthenticationState::query()->create([
        'user_id' => $counselor->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_generation' => 'consultation-read-effects-'.$suffix,
    ]);

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => hash('sha256', 'consultation-read-effects-'.$suffix),
        'first_name' => 'Read Effects',
        'last_name' => 'Applicant',
        'birthdate' => '2008-04-12',
        'sex' => 'female',
    ]);
    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'READ-EFFECTS-'.Str::upper($suffix).'-'.Str::upper(Str::random(6)),
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'preferred_program_code' => 'BSIT',
        'program_preference_1_code' => 'BSIT',
        'applicant_type' => 'new',
        'strand' => 'STEM',
        'gwa' => 92.5,
        'payload' => [
            'subject_grades' => ['shs_math' => 94, 'shs_sci' => 90, 'shs_eng' => 88],
            'riasec_flags' => [
                'riasec_r' => 1,
                'riasec_i' => 1,
                'riasec_a' => 0,
                'riasec_s' => 0,
                'riasec_e' => 0,
                'riasec_c' => 0,
            ],
        ],
    ]);

    return compact('attempt', 'counselor', 'institution', 'cycle', 'activation');
}

/** @return list<array<string, mixed>> */
function consultationAdmissionSupportReadEffectsRows(string $table): array
{
    return DB::table($table)
        ->orderBy('id')
        ->get()
        ->map(static fn (object $row): array => (array) $row)
        ->all();
}

/** Synthetic adapter response for the pinned TabFM consumer contract. */
function consultationAdmissionSupportReadEffectsTabFmResponse(ClientRequest $request): array
{
    $programs = collect($request['context_exemplars'])
        ->unique('program_code')
        ->sortBy('program_code')
        ->values();
    $featureDefinitions = $request['feature_definitions'];
    $queryApplicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];

    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $queryApplicant) && $queryApplicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
    }

    $recommendations = [];
    foreach ($programs as $program) {
        $score = $program['program_code'] === 'BSIT' ? 100 : 80;
        $recommendations[] = [
            'rank' => 0,
            'program_code' => $program['program_code'],
            'program_name' => $program['program_name'],
            'compatibility_index' => $score,
            'band' => 'high_match',
            'drivers' => ['Synthetic adapter response for consumer-contract coverage'],
        ];
    }
    usort($recommendations, static fn (array $left, array $right): int => ($right['compatibility_index'] <=> $left['compatibility_index'])
        ?: ($left['program_code'] <=> $right['program_code']));
    foreach ($recommendations as $position => &$recommendation) {
        $recommendation['rank'] = $position + 1;
    }
    unset($recommendation);

    return [
        'status' => 'evaluated',
        'engine_version' => '9.1.0',
        'inference_backend' => 'tabpfn_pytorch',
        'fallback_reason' => null,
        'provenance' => [
            'package' => 'tabpfn',
            'package_version' => '9.1.0',
            'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ],
        'execution_time_ms' => 1.25,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => $featureDefinitions,
        'recommendations' => $recommendations,
    ];
}

function consultationAdmissionSupportPrepareReadyAdvisedAttempt(AdmissionAttempt $attempt, User $counselor): void
{
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-READ-EFFECTS-'.$attempt->id,
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);
    ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'consultation-read-effects',
        'checksum' => hash('sha256', 'consultation-read-effects-'.$attempt->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/consultation-read-effects-'.$attempt->id.'.html',
        'preview_path' => 'results/consultation-read-effects-'.$attempt->id.'.html',
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $counselor->id,
        'decision_type' => 'approve',
        'notes' => 'Synthetic finalized counseling prerequisite.',
        'version' => 1,
        'idempotency_key' => 'consultation-read-effects-'.$attempt->id,
    ]);
}

test('Consultation view records sole append-only protected-view audit and leaves admission and snapshot state invariant', function (): void {
    ['attempt' => $attempt, 'counselor' => $counselor, 'activation' => $activation] = consultationAdmissionSupportReadEffectsFixture('view');
    $benchmarkSet = AdmissionBenchmarkSet::query()->findOrFail($activation->benchmark_set_id);
    $snapshot = AdmissionSupportSnapshot::query()->create([
        'admission_attempt_id' => $attempt->id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'version' => 1,
        'engine_slot' => 'tabfm_pairwise',
        'engine_version' => '9.1.0',
        'benchmark_activation_id' => $activation->id,
        'benchmark_hash' => $benchmarkSet->content_sha256,
        'input_digest' => hash('sha256', 'existing-consultation-read-effects-snapshot'),
        'payload' => [
            'slot' => 'tabfm_pairwise',
            'status' => 'evaluated',
            'evaluated_at' => now()->subHour()->toIso8601String(),
            'recommendations' => [['program_code' => 'BSIT', 'rank' => 1, 'compatibility_index' => 82.0]],
        ],
        'trigger_event' => 'existing_evaluation',
        'recorded_by_user_id' => $counselor->id,
        'created_at' => now()->subHour(),
    ]);
    expect(AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);

    $attemptTable = (new AdmissionAttempt)->getTable();
    $snapshotTable = (new AdmissionSupportSnapshot)->getTable();
    $poolTable = (new ProgramPool)->getTable();
    $selectionTable = (new ProgramSelection)->getTable();
    $auditIdsBefore = AuditEvent::query()->pluck('id')->all();
    $auditCountBefore = count($auditIdsBefore);
    $snapshotCountBefore = AdmissionSupportSnapshot::query()->count();
    $attemptCountBefore = AdmissionAttempt::query()->count();
    $attemptUpdatedAtBefore = DB::table($attemptTable)->where('id', $attempt->id)->value('updated_at');
    $poolCountBefore = ProgramPool::query()->count();
    $selectionCountBefore = ProgramSelection::query()->count();
    $attemptRowsBefore = consultationAdmissionSupportReadEffectsRows($attemptTable);
    $snapshotRowsBefore = consultationAdmissionSupportReadEffectsRows($snapshotTable);
    $poolRowsBefore = consultationAdmissionSupportReadEffectsRows($poolTable);
    $selectionRowsBefore = consultationAdmissionSupportReadEffectsRows($selectionTable);

    actingAsAdmissionSupportStaff($this, $counselor)
        ->get(route('admission-support.show', $attempt))
        ->assertOk();

    $newAuditEvents = AuditEvent::query()->whereNotIn('id', $auditIdsBefore)->get();
    $audit = $newAuditEvents->sole();

    expect(AuditEvent::query()->count())->toBe($auditCountBefore + 1)
        ->and($newAuditEvents)->toHaveCount(1)
        ->and($audit->event_type)->toBe('admission.support_projection_opened')
        ->and($audit->actor)->toBe((string) $counselor->id)
        ->and($audit->payload['attempt_id'])->toBe($attempt->id)
        ->and($audit->payload['institution_id'])->toBe($attempt->institution_id)
        ->and($audit->payload['academic_cycle_id'])->toBe($attempt->academic_cycle_id)
        ->and($audit->payload['role'])->toBe('guidance')
        ->and(AdmissionAttempt::query()->count())->toBe($attemptCountBefore)
        ->and(DB::table($attemptTable)->where('id', $attempt->id)->value('updated_at'))->toBe($attemptUpdatedAtBefore)
        ->and(consultationAdmissionSupportReadEffectsRows($attemptTable))->toBe($attemptRowsBefore)
        ->and(ProgramPool::query()->count())->toBe($poolCountBefore)
        ->and(consultationAdmissionSupportReadEffectsRows($poolTable))->toBe($poolRowsBefore)
        ->and(ProgramSelection::query()->count())->toBe($selectionCountBefore)
        ->and(consultationAdmissionSupportReadEffectsRows($selectionTable))->toBe($selectionRowsBefore)
        ->and(AdmissionSupportSnapshot::query()->count())->toBe($snapshotCountBefore)
        ->and(consultationAdmissionSupportReadEffectsRows($snapshotTable))->toBe($snapshotRowsBefore)
        ->and(AdmissionSupportSnapshot::query()->whereKey($snapshot->id)->exists())->toBeTrue();
});

test('Model recommendations have zero admission write authority and require explicit human counselor confirmation', function (): void {
    ['attempt' => $attempt, 'counselor' => $counselor, 'institution' => $institution, 'cycle' => $cycle, 'activation' => $activation] = consultationAdmissionSupportReadEffectsFixture('model');
    consultationAdmissionSupportPrepareReadyAdvisedAttempt($attempt, $counselor);

    $program = Program::query()
        ->where('institution_id', $institution->id)
        ->where('code', 'BSIT')
        ->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
        ['slots' => 5],
    );

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            consultationAdmissionSupportReadEffectsTabFmResponse($request),
            200,
        ),
    ]);

    $input = new AdmissionInputPayload(
        attemptId: (int) $attempt->id,
        institutionId: (int) $attempt->institution_id,
        academicCycleId: (int) $attempt->academic_cycle_id,
        gwa: 92.5,
        aptitudePercentiles: [
            'LOGIC' => 92.0,
            'PROFILE_QUANT' => 95.0,
            'SPATIAL' => 91.0,
            'PROFILE_VERBAL' => 78.0,
            'MEMORY' => 84.0,
            'LANGUAGE' => 80.0,
        ],
        aptitudeScales: [
            'LOGIC' => 'percentile_rank',
            'PROFILE_QUANT' => 'percentile_rank',
            'SPATIAL' => 'percentile_rank',
            'PROFILE_VERBAL' => 'percentile_rank',
            'MEMORY' => 'percentile_rank',
            'LANGUAGE' => 'percentile_rank',
        ],
    );
    $evaluation = app(TabFmPairwiseProvider::class)->evaluateAtActivation($input, $activation);
    $poolCountBefore = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();
    $selectionCountBefore = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count();

    expect($evaluation->status)->toBe('evaluated')
        ->and($evaluation->recommendations)->not->toBeEmpty()
        ->and($evaluation->recommendations[0]->programCode)->toBe($program->code)
        ->and($poolCountBefore)->toBe(0)
        ->and($selectionCountBefore)->toBe(0)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($poolCountBefore)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($selectionCountBefore);

    Http::assertSentCount(1);

    $poolRequest = [
        'default_program_code' => $program->code,
        'options' => [['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference']],
        'reason' => 'The counselor reviewed and confirmed the model recommendation.',
        'idempotency_key' => 'read-effects-pool-'.$attempt->id,
    ];

    actingAsAdmissionSupportStaff($this, $counselor)
        ->post(route('program-pool.set', $attempt), $poolRequest)
        ->assertForbidden();
    expect(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);

    $counselor->givePermissionTo(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE);
    actingAsAdmissionSupportStaff($this, $counselor)
        ->post(route('program-pool.set', $attempt), $poolRequest)
        ->assertRedirect();

    $pool = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    expect(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and($pool->actor_user_id)->toBe($counselor->id)
        ->and($selection->actor_user_id)->toBe($counselor->id)
        ->and($selection->program_id)->toBe($program->id)
        ->and($pool->options()->pluck('program_id')->all())->toBe([$program->id]);
});
