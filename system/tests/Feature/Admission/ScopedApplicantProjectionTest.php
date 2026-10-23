<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\PublishProgramAdvisingNoteRevision;
use App\Admission\Actions\SaveProgramAdvisingNote;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ProgramAdvisingNoteUsage;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThread;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

const SCOPED_PROJECTION_DEFAULT_PROGRAM = 'BSIT';

test('Program Head receives only Tier A summary for preference-only candidate and Guidance retains full support surface', function (): void {
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'projection-tier-a@example.test');
    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $revision = app(SaveProgramAdvisingNote::class)->handle($guidance, $program, $cycle, 'Reviewed program advice.', 0, 'Prepare advice', 'projection-note-save');
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $revision->note, $revision, 1, 'Review advice', 'projection-note-publish');
    $unpublished = app(SaveProgramAdvisingNote::class)->handle($guidance, $program, $cycle, 'Unpublished replacement must stay in Setup.', 1, 'Draft next advice', 'projection-note-v2', (int) $revision->program_advising_note_id);
    $secondNote = app(SaveProgramAdvisingNote::class)->handle($guidance, $program, $cycle, 'Second current published advice.', 0, 'Prepare second card', 'projection-second-note');
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $secondNote->note, $secondNote, 1, 'Review second advice', 'projection-second-note-publish');
    $unrelated = Program::query()->create(['institution_id' => $attempt->institution_id, 'code' => 'UNRELATED-NOTE', 'name' => 'Unrelated program', 'is_active' => true]);
    $otherRevision = app(SaveProgramAdvisingNote::class)->handle($guidance, $unrelated, $cycle, 'Unrelated published advice.', 0, 'Prepare unrelated advice', 'projection-other-note-save');
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $otherRevision->note, $otherRevision, 1, 'Review unrelated advice', 'projection-other-note-publish');

    $record = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $programHead);
    expect($record['tier'])->toBe('A')
        ->and($record)->not->toHaveKeys(['score_profile', 'consultation_outcome', 'recommendations', 'analysis', 'background', 'follow_ups']);

    actingAsMessagingActor($this, $programHead)
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/ApplicantAdmissionSupport')
            ->where('role', 'program_head')
            ->where('ph_record.tier', 'A')
            ->where('support', null)
            ->where('available_programs', [])
            ->where('published_advising_notes', [])
            ->where('online_consultation.visible', false)
            ->missing('support.score_profile')
            ->missing('support.models'));
    actingAsMessagingActor($this, $guidance)
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/ApplicantAdmissionSupport')
            ->where('role', 'guidance')
            ->has('support.score_profile')
            ->has('published_advising_notes', 2)
            ->where('published_advising_notes.0.revision_id', $revision->id)
            ->where('published_advising_notes.0.content', 'Reviewed program advice.')
            ->where('published_advising_notes.0.author_name', $guidance->name)
            ->where('published_advising_notes.0.publisher_name', $guidance->name)
            ->where('published_advising_notes.0.details_url', route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id, 'note_id' => $revision->program_advising_note_id]))
            ->where('published_advising_notes.0.usage_receipt', null)
            ->where('published_advising_notes.0.can_record_use', true)
            ->where('published_advising_notes.1.revision_id', $secondNote->id)
            ->where('published_advising_notes.1.content', 'Second current published advice.')
            ->where('online_consultation.visible', true)
            ->where('online_consultation.exception_mode', false)
            ->has('support.models'));
    expect(ProgramAdvisingNoteUsage::query()->count())->toBe(0);
    actingAsMessagingActor($this, $programHead)->postJson(route('setup.admission-support.advising-note.use', $attempt), [
        'source_revision_id' => $revision->id, 'idempotency_key' => 'tier-a-note-denied',
    ])->assertForbidden();
    actingAsMessagingActor($this, $guidance)->postJson(route('setup.admission-support.advising-note.use', $attempt), [
        'source_revision_id' => $otherRevision->id, 'idempotency_key' => 'unrelated-note-denied',
    ])->assertForbidden();
    actingAsMessagingActor($this, $guidance)->postJson(route('setup.admission-support.advising-note.use', $attempt), [
        'source_revision_id' => $unpublished->id, 'idempotency_key' => 'draft-note-denied',
    ])->assertUnprocessable();
});

test('Program Head Tier B requires acknowledged current selection and serializes only target-program facts', function (): void {
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'projection-tier-b@example.test');
    $assignment = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->latest('id')
        ->firstOrFail();
    $gradingSession = GradingSession::query()
        ->where('exam_session_id', $assignment->exam_session_id)
        ->where('status', GradingSession::STATUS_FINALIZED)
        ->latest('id')
        ->firstOrFail();
    $area = AptitudeArea::query()
        ->where('code', 'VERBAL')
        ->where('version', 1)
        ->firstOrFail();
    $converted = $area->convertScore(85);
    ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $area->id,
        'score' => '85',
        'converted_score' => $converted?->output_value,
        'source' => ScoreRecord::SOURCE_MANUAL,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'synthetic-projection-tier-b-score-'.$attempt->id.'-'.$area->id,
    ]);
    $document = ResultDocument::query()->whereKey($attempt->current_result_document_id)->firstOrFail();
    $snapshotPayload = $document->snapshot_payload_json;
    $snapshotPayload['scores'] = [[
        'area_code' => $area->code,
        'domain' => $area->name,
        'converted' => 'Frozen released-document score',
        'scale' => 'converted',
        'raw' => '',
    ]];
    $document->forceFill(['snapshot_payload_json' => $snapshotPayload])->save();
    ResultRelease::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('result_document_id', $document->id)
        ->where('is_current', true)
        ->firstOrFail()
        ->forceFill(['physical_handover_at' => now()])
        ->save();

    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'projection-pool-tier-b');

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $query = app(ProgramHeadApplicantQueries::class);

    $summaryRecord = $query->protectedRecord($attempt, $program, $programHead);
    expect($summaryRecord['tier'])->toBe('A')
        ->and($summaryRecord)->not->toHaveKey('score_profile');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'projection-ack-tier-b');
    $missingRecord = $query->protectedRecord($attempt, $program, $programHead);
    $missingAnalysis = $missingRecord['analysis'];
    expect($missingAnalysis['status'])->toBeIn(['no_analysis', 'result_not_ready'])
        ->and($missingAnalysis['source_kind'])->toBe('active')
        ->and($missingAnalysis['current_applicable'])->toBeFalse()
        ->and($missingAnalysis['recommendations'])->toBe([])
        ->and($missingRecord['background']['vocational_profile']['scores'])->toBe([])
        ->and($missingRecord['background']['vocational_profile']['emergent_themes'])->toBe([]);
    $attempt->payload = [
        'supplemental_evidence' => [
            'activities_reviewed' => true,
            'vocational_activities' => ['hardware_tech', 'software_algorithms'],
            'counselor_notes' => 'PRIVATE COUNSELOR SENTINEL',
            'private_answer_sheet' => 'RAW ANSWER SENTINEL',
        ],
        'program_preferences' => ['OTHER-PROGRAM-SENTINEL'],
    ];
    $attempt->save();
    $checkpoint = str_repeat('a', 64);
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.checkpoint_id' => 'approved-test-checkpoint',
        'admission_engine.tabfm.checkpoint_sha256' => $checkpoint,
        'admission_engine.tabfm.package_version' => '9.1.0',
    ]);
    AdmissionSupportSnapshot::query()->create([
        'admission_attempt_id' => $attempt->id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'version' => 1,
        'engine_slot' => 'tabfm_pairwise',
        'engine_version' => '9.1.0',
        'input_digest' => str_repeat('b', 64),
        'payload' => [
            'status' => 'evaluated',
            'engine_version' => '9.1.0',
            'benchmark_version' => 'v2',
            'evaluated_at' => '2026-10-20T10:00:00+00:00',
            'inference_backend' => 'tabpfn_pytorch',
            'fallback_reason' => null,
            'modality_coverage' => [
                'inference_provenance' => [
                    'package' => 'tabpfn',
                    'package_version' => '9.1.0',
                    'checkpoint_identity' => 'approved-test-checkpoint',
                    'checkpoint_sha256' => $checkpoint,
                    'private_diagnostic' => 'PROVENANCE SENTINEL',
                ],
                'feature_coverage' => ['PRIVATE FEATURE SENTINEL'],
            ],
            'recommendations' => [
                ['program_code' => $program->code, 'program_name' => $program->name, 'rank' => 8,
                    'compatibility_index' => 88, 'band' => 'high_match', 'passed_cutoff' => true,
                    'drivers' => ['OWN DRIVER'], 'radar_comparison' => null, 'private_nested' => 'NESTED SENTINEL'],
                ['program_code' => 'OTHER-PROGRAM-SENTINEL', 'program_name' => 'OTHER NAME SENTINEL', 'rank' => 1,
                    'compatibility_index' => 99, 'drivers' => ['CROSS DRIVER SENTINEL']],
            ],
            'recorded_result' => ['private_recorded_payload' => 'RECORDED SENTINEL'],
            'fallback_reason_detail' => 'DIAGNOSTIC SENTINEL',
            'message' => 'UNSAFE SOURCE MESSAGE SENTINEL',
        ],
        'trigger_event' => 'projection-test',
        'recorded_by_user_id' => $guidance->id,
    ]);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $revision = app(SaveProgramAdvisingNote::class)->handle($guidance, $program, $cycle, 'Protected program advice.', 0, 'Prepare advice', 'projection-tier-b-save');
    app(PublishProgramAdvisingNoteRevision::class)->handle($guidance, $revision->note, $revision, 1, 'Review advice', 'projection-tier-b-publish');
    actingAsMessagingActor($this, $programHead)->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->has('published_advising_notes', 1)
            ->where('published_advising_notes.0.program_id', $program->id)
            ->where('published_advising_notes.0.revision_id', $revision->id)
            ->where('published_advising_notes.0.usage_receipt', null)
            ->where('ph_record.analysis.status', 'evaluated')
            ->where('ph_record.analysis.current_applicable', false)
            ->where('ph_record.analysis.recommendations', [])
            ->where('ph_record.background.vocational_profile.status', 'reviewed')
            ->where('support', null));
    $selectionBefore = $attempt->programSelections()->get()->toArray();
    $usageResponse = actingAsMessagingActor($this, $programHead)->postJson(route('setup.admission-support.advising-note.use', $attempt), [
        'source_revision_id' => $revision->id, 'idempotency_key' => 'projection-tier-b-use',
    ])->assertOk()->assertJsonPath('usage.revision_id', $revision->id)->assertJsonPath('usage.actor_user_id', $programHead->id);
    $this->postJson(route('setup.admission-support.advising-note.use', $attempt), [
        'source_revision_id' => $revision->id, 'idempotency_key' => 'projection-tier-b-use',
    ])->assertOk()->assertJsonPath('usage.id', $usageResponse->json('usage.id'));
    expect(ProgramAdvisingNoteUsage::query()->count())->toBe(1)
        ->and($attempt->programSelections()->get()->toArray())->toBe($selectionBefore);
    $this->get(route('admission-support.show', $attempt))->assertInertia(fn ($page) => $page
        ->where('published_advising_notes.0.usage_receipt.id', $usageResponse->json('usage.id')));

    $record = $query->protectedRecord($attempt, $program, $programHead);
    expect($record['tier'])->toBe('B')
        ->and($record['score_profile']['series'])->not->toBeEmpty()
        ->and($record['score_profile']['series'])->toBe([
            [
                'area_code' => $area->code,
                'area_name' => $area->name,
                'score' => 'Frozen released-document score',
                'scale' => 'converted',
                'percentile' => null,
            ],
        ])
        ->and($record['selection']['program_code'])->toBe($program->code)
        ->and($record)->not->toHaveKeys([
            'raw_scores',
            'conversion_table',
            'applicant_evidence',
            'consultation_history',
            'consultation_outcome',
            'models',
            'recommendations',
        ])
        ->and($record['analysis']['status'])->toBe('evaluated')
        ->and($record['analysis']['source_kind'])->toBe('recorded')
        ->and($record['analysis']['current_applicable'])->toBeFalse()
        ->and($record['analysis']['recommendations'])->toBe([])
        ->and($record['analysis']['message'])->toContain('does not apply')
        ->and($record['background']['vocational_profile']['status'])->toBe('reviewed')
        ->and($record['background']['vocational_profile']['scores']['R'])->toBe(33.3)
        ->and(array_keys($record['background']['vocational_profile']['program_targets']))->toBe([$program->code])
        ->and($record['background']['vocational_profile']['emergent_themes'])->not->toBeEmpty()
        ->and(json_encode($record))->not->toContain('SENTINEL')
        ->and(json_encode($record))->not->toContain('OTHER-PROGRAM')
        ->and(array_column($record['score_profile']['series'], 'raw_score'))->toBe([]);

    foreach ($record['score_profile']['series'] as $score) {
        expect($score)->toHaveKeys(['area_code', 'area_name', 'score', 'scale', 'percentile'])
            ->and($score)->not->toHaveKeys(['raw_score', 'converted_label', 'source']);
    }

    foreach ($record['follow_ups'] as $followUp) {
        expect($followUp['program_code'])->toBe($program->code)
            ->and($followUp)->not->toHaveKeys(['reason', 'resolution_reason']);
    }
    (new AdmissionBenchmarkSeeder)->run();
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
    expect($activation)->not->toBeNull();
    $benchmarkSet = $activation->benchmarkSet()->with('features')->firstOrFail();
    $currentScoreProjection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $currentInput = AdmissionInputPayload::fromAttempt(
        $attempt,
        ConsultationScoreProjection::admissionInputSeries($currentScoreProjection),
    );
    $axisSources = [
        'logic' => 'LOGIC', 'verbal' => 'PROFILE_VERBAL', 'numerical' => 'PROFILE_QUANT',
        'spatial' => 'SPATIAL', 'memory' => 'MEMORY', 'language' => 'LANGUAGE',
    ];
    $axisKeys = array_keys($axisSources);
    $radarValues = array_fill_keys($axisKeys, 50.0);
    $radar = [
        'version' => 1,
        'scale' => ['kind' => 'percentile', 'min' => 0, 'max' => 100],
        'axes' => array_map(
            static fn (string $key): array => ['key' => $key, 'label' => ucfirst($key), 'source_key' => $axisSources[$key], 'private_source' => 'PRIVATE AXIS SENTINEL'],
            $axisKeys,
        ),
        'student' => ['values' => $radarValues, 'private_nested' => 'RADAR SENTINEL'],
        'profiles' => [
            'cutoff' => ['tier' => 3, 'values' => $radarValues],
            'regular' => ['tier' => 2, 'values' => $radarValues],
            'honors' => ['tier' => 1, 'values' => $radarValues],
            'other_program' => ['private_nested' => 'CROSS RADAR SENTINEL'],
        ],
        'private_radar_field' => 'RADAR SENTINEL',
    ];
    $currentSnapshotPayload = [
        'status' => 'evaluated',
        'engine_version' => '9.1.0',
        'benchmark_version' => 'v'.$benchmarkSet->version,
        'evaluated_at' => '2026-10-20T10:00:00+00:00',
        'inference_backend' => 'tabpfn_pytorch',
        'fallback_reason' => null,
        'modality_coverage' => [
            'inference_provenance' => [
                'package' => 'tabpfn',
                'package_version' => '9.1.0',
                'checkpoint_identity' => 'approved-test-checkpoint',
                'checkpoint_sha256' => $checkpoint,
                'private_diagnostic' => 'PROVENANCE SENTINEL',
            ],
            'feature_coverage' => ['PRIVATE COVERAGE SENTINEL'],
        ],
        'assessment_context' => $currentScoreProjection['assessment_context'],
        'evaluation_context' => [
            'benchmark_activation_id' => (int) $activation->id,
            'benchmark_set_id' => (int) $benchmarkSet->id,
            'institution_id' => (int) $attempt->institution_id,
            'academic_cycle_id' => (int) $attempt->academic_cycle_id,
            'schema_digest' => (string) $benchmarkSet->schema_sha256,
            'content_digest' => (string) $benchmarkSet->content_sha256,
            'input_digest' => $currentInput->digest(),
        ],
        'recommendations' => [
            [
                'program_code' => $program->code,
                'program_name' => $program->name,
                'rank' => 7,
                'compatibility_index' => 88,
                'band' => 'high_match',
                'passed_cutoff' => true,
                'drivers' => ['Own-program driver'],
                'radar_comparison' => $radar,
                'private_nested' => 'NESTED SENTINEL',
            ],
            [
                'program_code' => 'BSN',
                'program_name' => 'BSN CROSS-PROGRAM SENTINEL',
                'rank' => 1,
                'compatibility_index' => 99,
                'drivers' => ['CROSS-PROGRAM DRIVER SENTINEL'],
                'radar_comparison' => ['private' => 'CROSS RADAR SENTINEL'],
            ],
        ],
        'message' => 'UNSAFE SOURCE MESSAGE SENTINEL',
    ];
    AdmissionSupportSnapshot::query()->create([
        'admission_attempt_id' => $attempt->id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'version' => 2,
        'engine_slot' => 'tabfm_pairwise',
        'engine_version' => '9.1.0',
        'benchmark_activation_id' => $activation->id,
        'benchmark_hash' => $benchmarkSet->content_sha256,
        'input_digest' => $currentInput->digest(),
        'payload' => $currentSnapshotPayload,
        'trigger_event' => 'projection-current-test',
        'recorded_by_user_id' => $guidance->id,
    ]);
    $snapshotCount = AdmissionSupportSnapshot::query()->count();
    $currentRecord = $query->protectedRecord($attempt, $program, $programHead);
    expect($currentRecord['analysis']['status'])->toBe('evaluated')
        ->and($currentRecord['analysis']['source_kind'])->toBe('recorded')
        ->and($currentRecord['analysis']['current_applicable'])->toBeTrue()
        ->and($currentRecord['analysis']['recommendations'])->toHaveCount(1)
        ->and($currentRecord['analysis']['recommendations'][0])->toMatchArray([
            'program_code' => $program->code,
            'program_name' => $program->name,
            'compatibility_index' => 88.0,
            'band' => 'high_match',
            'passed_cutoff' => true,
            'drivers' => ['Own-program driver'],
        ])
        ->and($currentRecord['analysis']['recommendations'][0])->not->toHaveKeys(['rank', 'private_nested'])
        ->and($currentRecord['analysis']['recommendations'][0]['radar_comparison']['axes'][0])
        ->toBe(['key' => 'logic', 'label' => 'Logic', 'source_key' => 'LOGIC'])
        ->and(json_encode($currentRecord))->not->toContain('SENTINEL')
        ->and(json_encode($currentRecord))->not->toContain('BSN')
        ->and(AdmissionSupportSnapshot::query()->count())->toBe($snapshotCount);
});

test('Program Head deep link denies unrelated attempts and support JSON is Guidance-scope only', function (): void {
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'projection-denied@example.test');
    $outsider = User::factory()->create();

    actingAsMessagingActor($this, $outsider)
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'denied')
            ->where('attempt', null)
            ->where('online_consultation.visible', false)
            ->where('online_consultation.can_start', false)
            ->missing('support'));

    actingAsMessagingActor($this, $outsider)
        ->getJson(route('admission-support.support', $attempt))
        ->assertForbidden()
        ->assertJson(['page_state' => 'denied']);
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    actingAsMessagingActor($this, $programHead)
        ->getJson(route('admission-support.support', $attempt))
        ->assertForbidden()
        ->assertJson(['page_state' => 'denied']);
    $programHead->staffProfile()->firstOrFail()->update(['status' => StaffProfile::STATUS_INACTIVE]);
    actingAsMessagingActor($this, $programHead)
        ->get(route('admission-support.show', $attempt))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Your staff account is inactive or suspended. Please contact HR.']);

    actingAsMessagingActor($this, $guidance)
        ->getJson(route('admission-support.support', $attempt))
        ->assertOk()
        ->assertJsonStructure(['support' => ['score_profile', 'program_pool', 'consultation_evidence']]);
});
test('Program Head applicant projection isolates threads to the resolved program', function (): void {
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'projection-two-program-threads@example.test');
    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    $otherProgram = Program::query()->where('code', 'BA-Psych')->firstOrFail();
    $programHeads = [
        $program->id => User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail(),
        $otherProgram->id => User::query()->where('email', 'dean.cas@nexiam.test')->firstOrFail(),
    ];
    $openThread = app(SendAdmissionSupportMessage::class);

    foreach ([$program, $otherProgram] as $index => $threadProgram) {
        ProgramFollowUp::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $threadProgram->id,
            'opened_by_user_id' => $guidance->id,
            'status' => ProgramFollowUp::STATUS_OPEN,
            'reason' => 'Program-scoped case context',
            'idempotency_key' => 'projection-followup-'.$index,
            'version' => 1,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $openThread->openInternalThread(
            $attempt,
            (int) $threadProgram->id,
            'Program '.$threadProgram->code,
            'Reviewing this applicant for '.$threadProgram->code.'.',
            $programHeads[$threadProgram->id],
            $index === 0
                ? '10000000-0000-4000-8000-000000000001'
                : '10000000-0000-4000-8000-000000000002',
        );
    }
    $applicantThread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt,
        (int) $program->id,
        'Applicant support',
        'Applicant-visible support conversation.',
        $programHeads[$program->id],
        '10000000-0000-4000-8000-000000000003',
    );
    $foreignThreadId = (string) RecordThread::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('program_id', $otherProgram->id)
        ->value('id');

    $response = actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ph_program.code', $program->code)
            ->has('threads', 2)
            ->where('threads.0.program_code', $program->code)
            ->where('threads.0.thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
            ->where('threads.0.can_read', true)
            ->where('threads.0.can_send', true)
            ->where('threads.1.program_code', $program->code)
            ->where('threads.1.thread_type', RecordThread::TYPE_APPLICANT_SUPPORT)
            ->where('threads.1.snapshot_url', route('case-conversations.show', ['thread' => (string) $applicantThread->id]))
            ->where('threads.1.can_read', true)
            ->where('threads.1.can_send', true)
            ->where('threads.1.send_url', route('case-conversations.messages.store', ['thread' => (string) $applicantThread->id]))
            ->where('threads.1.read_url', route('case-conversations.read', ['thread' => (string) $applicantThread->id]))
            ->where('threads.1.html_url', route('admission-support.show', [
                'attempt' => (string) $attempt->id,
                'thread_id' => (string) $applicantThread->id,
                'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
                'program_code' => $program->code,
                'cycle_code' => $attempt->academicCycle->code,
                'tab' => 'threads',
            ]))
            ->where('selected_conversation', null)
            ->missing('threads.0.messages')
            ->missing('threads.1.messages'));

    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', [
            'attempt' => $attempt->id,
            'thread_id' => $applicantThread->id,
            'program_code' => $program->code,
            'cycle_code' => $attempt->academicCycle->code,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('threads', 2)
            ->where('threads.1.id', (string) $applicantThread->id)
            ->where('threads.1.thread_type', RecordThread::TYPE_APPLICANT_SUPPORT)
            ->missing('threads.0.messages')
            ->missing('threads.1.messages')
            ->where('selected_conversation.thread.id', (string) $applicantThread->id)
            ->where('selected_conversation.messages.0.body', 'Applicant-visible support conversation.')
            ->where('selected_conversation.principal_key', fn ($key) => is_string($key) && $key !== '')
            ->where('selected_conversation.attempt_key', fn ($key) => is_string($key) && $key !== ''));

    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', [
            'attempt' => $attempt->id,
            'thread_id' => $applicantThread->id,
            'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        ]))
        ->assertNotFound();

    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', [
            'attempt' => $attempt->id,
            'thread_id' => $foreignThreadId,
            'program_code' => $otherProgram->code,
            'cycle_code' => $attempt->academicCycle->code,
        ]))
        ->assertNotFound();

    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', [
            'attempt' => $attempt->id,
            'thread_id' => $applicantThread->id,
            'program_code' => $program->code,
            'cycle_code' => 'FOREIGN-CYCLE',
        ]))
        ->assertNotFound();

    $phToRevoke = $programHeads[$program->id];
    foreach ($phToRevoke->roles as $role) {
        $role->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    }
    $phToRevoke->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('threads', []));

    actingAsMessagingActor($this, $programHeads[$program->id])
        ->get(route('admission-support.show', [
            'attempt' => $attempt->id,
            'thread_id' => $applicantThread->id,
        ]))
        ->assertNotFound();
});

test('mandatory protected-view audit failure propagates instead of downgrading to Tier A', function (): void {
    [$attempt] = buildMessagingReadyAttempt($this, 'projection-audit-failure@example.test');
    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => User::query()->where('email', 'guidance.counselor@nexiam.test')->value('id'),
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'Protected-view audit regression',
        'idempotency_key' => 'projection-audit-followup',
        'version' => 1,
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);

    $this->withoutExceptionHandling();
    expect(fn () => app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $programHead))
        ->toThrow(RuntimeException::class, 'audit unavailable');
});
test('Guidance support projection fails closed when its mandatory open audit fails', function (): void {
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'projection-guidance-audit-failure@example.test');
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);

    $this->withoutExceptionHandling();
    expect(fn () => actingAsMessagingActor($this, $guidance)->getJson(route('admission-support.support', $attempt)))
        ->toThrow(RuntimeException::class, 'audit unavailable');
});

test('program Operations links preserve authorized institution and active cycle context', function (): void {
    seedE2Foundation();
    $this->withoutVite();
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    actingAsMessagingActor($this, $actor)->get(route('program-head.show', $program->code))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('page_state', 'ready')
        ->where('setup_links.studio', route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->where('setup_links.quotas', route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id])));
    $actor->syncRoles([]);
    $actor->syncPermissions([CapabilityCatalog::PROGRAM_HEAD_VIEW]);
    $this->get(route('program-head.show', $program->code))->assertInertia(fn ($page) => $page
        ->where('page_state', 'ready')->missing('setup_links.studio')->missing('setup_links.quotas'));
    $otherInstitution = Institution::query()->create(['code' => 'PROJECTION-OTHER', 'name' => 'Other institution']);
    $foreignProgram = Program::query()->create(['institution_id' => $otherInstitution->id, 'code' => 'FOREIGN-PROJECTION', 'name' => 'Foreign program', 'is_active' => true]);
    $actor->staffProfile()->firstOrFail()->update(['institution_id' => $otherInstitution->id, 'program_codes' => [$program->code, $foreignProgram->code]]);
    $this->get(route('program-head.show', $program->code))->assertInertia(fn ($page) => $page
        ->where('page_state', 'denied')->missing('dashboard')->missing('setup_links'));
    $this->get(route('program-head.index'))->assertInertia(fn ($page) => $page->where('programs', []));
});

test('Dean comparison contains actual minimum cutoff cells only for the currently owned college', function (): void {
    seedE2Foundation();
    $this->withoutVite();
    (new AdmissionBenchmarkSeeder)->run();
    $actor = User::query()->where('email', 'dean.ccit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $college = Department::query()->create(['institution_id' => $program->institution_id, 'code' => 'PROJECTION-DEAN', 'name' => 'Synthetic comparison college', 'dean_user_id' => $actor->id, 'is_active' => true]);
    $program->update(['department_id' => $college->id]);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $set = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->benchmarkSet;
    $cutoff = $set->exemplars()->where('program_id', $program->id)->where('tier_level', 3)->with('values.feature')->firstOrFail();
    $expectedValues = $cutoff->values->mapWithKeys(fn ($value) => [$value->feature->feature_key => $value->numeric_value ?? $value->categorical_value])->all();
    actingAsMessagingActor($this, $actor)->get(route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('governance.comparison.college.id', $program->department_id)
        ->where('governance.comparison.set_id', $set->id)
        ->where('governance.comparison.rows', function ($rows) use ($program, $cutoff, $expectedValues): bool {
            $rows = collect($rows);
            $target = $rows->firstWhere('program.id', $program->id);
            expect($target['tier_level'])->toBe(3)
                ->and($target['tier_label'])->toBe($cutoff->tier_label)
                ->and($target['values'])->toEqual($expectedValues);
            $allowedIds = Program::query()->where('department_id', $program->department_id)->where('institution_id', $program->institution_id)->pluck('id')->all();
            foreach ($rows as $row) {
                expect($row['program']['id'])->toBeIn($allowedIds);
            }

            return true;
        }));
    Department::query()->whereKey($program->department_id)->update(['dean_user_id' => null]);
    $actor->staffProfile()->firstOrFail()->update(['program_codes' => [$program->code]]);
    $this->get(route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('governance.comparison', null));
});

test('Program Head program operational workspace projects bounded applicant conversations for current cycle and denies mismatched cycle', function (): void {
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'projection-ph-conversations@example.test');
    $program = Program::query()->where('code', SCOPED_PROJECTION_DEFAULT_PROGRAM)->firstOrFail();
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => $guidance->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'Program-scoped case context',
        'idempotency_key' => 'projection-ph-conversations-followup',
        'version' => 1,
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $applicantThread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt,
        (int) $program->id,
        'Applicant admissions inquiry',
        'Inquiring about program admission requirements.',
        $programHead,
        '10000000-0000-4000-8000-000000000099',
    );

    actingAsMessagingActor($this, $programHead)
        ->get(route('program-head.show', $program->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'ready')
            ->has('applicant_conversations.data', 1)
            ->where('applicant_conversations.data.0.id', (string) $applicantThread->id)
            ->where('applicant_conversations.data.0.program_code', $program->code)
            ->where('applicant_conversations.data.0.cycle_code', $cycle->code)
            ->where('applicant_conversations.data.0.subject', 'Applicant admissions inquiry')
            ->where('applicant_conversations.data.0.html_url', route('admission-support.show', [
                'attempt' => (string) $attempt->id,
                'thread_id' => (string) $applicantThread->id,
                'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
                'program_code' => $program->code,
                'cycle_code' => $cycle->code,
                'tab' => 'threads',
            ]))
            ->where('applicant_conversations.data.0.applicant.reference_code', $attempt->reference_code));

    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $attempt->institution_id,
        'code' => 'FOREIGN-PH-CYCLE',
        'name' => 'Foreign Academic Cycle',
        'opens_on' => now()->addYear(),
        'closes_on' => now()->addYears(2),
        'is_active' => false,
    ]);
    $programHead->staffProfile()->firstOrFail()->update(['active_cycle_code' => $foreignCycle->code]);
    actingAsMessagingActor($this, $programHead)
        ->get(route('program-head.show', $program->code))
        ->assertInertia(fn ($page) => $page->where('page_state', 'denied'));
});
