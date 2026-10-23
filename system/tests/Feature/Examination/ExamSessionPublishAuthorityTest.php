<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Application\Capabilities\Handlers\Exams\ExamSessionPublishHandler;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Queries\ExamSessionDetailQuery;
use App\Examination\Support\ActionExecutionIdentity;
use App\Examination\Support\ExamSessionPresenter;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\StaffProfile;
use App\Models\User;
use App\Scheduling\CandidatePoolQuery;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Database\Seeders\CohortDistributionDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
    $this->settingsAuthority = User::factory()->create();
    $this->settingsAuthority->staffProfile()->create([
        'institution_id' => Institution::current()->id,
        'office' => 'registrar',
        'active_cycle_code' => $this->cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $this->settingsAuthority->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
});

function pubAuthAcceptedApplicant(User $registrar, string $email, string $lastName = 'Candidate'): AdmissionAttempt
{
    $submitted = submitSyntheticApplicant($email, $lastName);

    return app(AcceptAdmissionAttempt::class)->handle($submitted['attempt'], $registrar, 1);
}

function pubAuthDraftSession(
    User $guidance,
    AcademicCycle $cycle,
    Room $room,
    string $code,
    ?SeatingArrangement $arrangement = null,
    int $dayOffset = 4,
): ExamSession {
    return app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        $code,
        now()->addDays($dayOffset)->setTime(9, 0)->toDateTimeString(),
        now()->addDays($dayOffset)->setTime(11, 0)->toDateTimeString(),
        $guidance,
        $arrangement === null ? ExamSession::SEATING_MODE_SIMPLE_CAPACITY : ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        null,
        $arrangement,
    );
}

function assertPublishBlockedEverywhere(ExamSession $session, string $expectedReason): void
{
    $fresh = $session->fresh(['room', 'seatingArrangement']);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($fresh);
    $presenterReason = app(ExamSessionPresenter::class)->publishBlockReason($fresh);
    $detail = app(ExamSessionDetailQuery::class)->query($fresh->id, (int) $fresh->academicCycle->institution_id);
    $projection = ($detail['forXiamSafe'])();

    expect($preflight['ok'])->toBeFalse()
        ->and($preflight['reason'])->toBe($expectedReason)
        ->and($preflight['roster_material_fingerprint'])->toBeNull()
        ->and($presenterReason)->toBe($expectedReason)
        ->and($projection['is_publishable'])->toBeFalse()
        ->and($projection['publish_block_reason'])->toBe($expectedReason);

    expect(fn () => app(ExamSessionPublishHandler::class)->handle([
        'session_id' => $fresh->id,
        'expected_version' => $fresh->version,
        'execute' => false,
    ]))->toThrow(RuntimeException::class, $expectedReason);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    expect(fn () => app(PublishExamSession::class)->handle($fresh, $guidance, $fresh->version))
        ->toThrow(RuntimeException::class, $expectedReason);

    expect($fresh->fresh()->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($fresh->fresh()->version)->toBe($fresh->version)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $fresh->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('event_type', 'exam.session_published')->where('payload->exam_session_id', $fresh->id)->count())->toBe(0);
}

test('publishes an operational session without paper and freezes a simple-capacity logistics plan', function (): void {
    $session = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-AUTH-OK');
    $attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.ok@example.test', 'Positive');
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);

    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $presenterReason = app(ExamSessionPresenter::class)->publishBlockReason($session->fresh());
    $detail = app(ExamSessionDetailQuery::class)->query($session->id, (int) $session->academicCycle->institution_id);
    $projection = ($detail['forXiamSafe'])();
    $proposal = app(ExamSessionPublishHandler::class)->handle([
        'session_id' => $session->id,
        'expected_version' => $session->version,
        'execute' => false,
    ]);

    expect($preflight['ok'])->toBeTrue()
        ->and($preflight['reason'])->toBeNull()
        ->and($preflight['roster_material_fingerprint'])->toBeString()->not->toBeEmpty()
        ->and($presenterReason)->toBeNull()
        ->and($projection['is_publishable'])->toBeTrue()
        ->and($projection['publish_block_reason'])->toBeNull()
        ->and($proposal['mode'])->toBe('propose')
        ->and($proposal['impact']['roster_material_fingerprint'])->toBe($preflight['roster_material_fingerprint'])
        ->and($preflight['checks'])->not->toContain('materials_selected', 'booklet_usable', 'required_sets_available');

    $published = app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $preflight['roster_material_fingerprint'],
    );
    $plan = ExamSessionLogisticsPlan::query()
        ->where('exam_session_id', $published->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($published->version)->toBe($session->version + 1)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->capacity_limit)->toBe((int) $this->room->capacity)
        ->and($plan->seats()->count())->toBe(0)
        ->and($assignment->fresh()->activeLogisticsSeatAllocation)->toBeNull()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'exam.session_published')->where('payload->exam_session_id', $published->id)->count())->toBe(1);
});

test('confirmed publication replays its Action receipt without repeating logistics, audit, or notification effects', function (): void {
    $session = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-RECEIPT');
    $attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.receipt@example.test', 'Receipt');
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $identity = new ActionExecutionIdentity(
        executionId: (string) Str::uuid(),
        capabilityId: 'exams.session.publish@1',
        canonicalDigest: str_repeat('d', 64),
    );
    $action = app(PublishExamSession::class);
    $first = $action->handleConfirmed(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $preflight['roster_material_fingerprint'],
        $identity,
    );
    $planCount = ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->count();
    $auditCount = AuditEvent::query()
        ->where('event_type', 'exam.session_published')
        ->where('payload->exam_session_id', $session->id)
        ->count();
    $eventId = hash('sha256', "exam.session_published:{$session->id}:v{$first->safeResult['version']}");
    $deliveryCount = DB::table('notification_deliveries')->where('event_id', $eventId)->count();

    $replay = $action->handleConfirmed(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $preflight['roster_material_fingerprint'],
        $identity,
    );

    expect($replay->replayed)->toBeTrue()
        ->and($replay->safeResult)->toEqual($first->safeResult)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->count())->toBe($planCount)
        ->and($auditCount)->toBe(1)
        ->and(AuditEvent::query()
            ->where('event_type', 'exam.session_published')
            ->where('payload->exam_session_id', $session->id)
            ->count())->toBe($auditCount)
        ->and(DB::table('notification_deliveries')->where('event_id', $eventId)->count())->toBe($deliveryCount);
});

test('publishes with no selected paper or seating arrangement and records only logistics state', function (): void {
    $session = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-AUTH-NOMAT');
    $attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.nomat@example.test', 'NoMat');
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);

    $published = app(PublishExamSession::class)->handle($session, $this->guidance, $session->version);
    $plan = ExamSessionLogisticsPlan::query()
        ->where('exam_session_id', $published->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->capacity_limit)->toBe((int) $this->room->capacity)
        ->and($plan->seating_arrangement_id)->toBeNull()
        ->and($plan->seats()->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse();
});

test('publication rejects a stale seating-arrangement fingerprint then freezes the current exact arrangement identity', function (): void {
    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $this->settingsAuthority,
        true,
        Institution::current()->examFeaturePolicyVersion(),
    );
    $arrangement = SeatingArrangement::factory()->create([
        'name' => 'Publication fingerprint arrangement',
        'normalized_name' => 'publication fingerprint arrangement',
        'rows' => 2,
        'columns' => 3,
    ]);
    $session = pubAuthDraftSession(
        $this->guidance,
        $this->cycle,
        $this->room,
        'EX-PUB-STALE-ARRANGEMENT',
        arrangement: $arrangement,
    );
    $attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.stale-arr@example.test', 'Stale Arrangement');
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $arrangement->forceFill(['lock_version' => $arrangement->lock_version + 1])->save();

    expect(fn () => app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $preflight['roster_material_fingerprint'],
    ))->toThrow(RuntimeException::class, 'Stale exam session roster or logistics state.');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('event_type', 'exam.session_published')->where('payload->exam_session_id', $session->id)->count())->toBe(0);

    $currentPreflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $published = app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $currentPreflight['roster_material_fingerprint'],
    );
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $published->id)->where('is_current', true)->firstOrFail();

    expect($plan->seating_arrangement_id)->toBe($arrangement->id)
        ->and($plan->arrangement_lock_version)->toBe($arrangement->fresh()->lock_version)
        ->and($plan->arrangement_rows)->toBe($arrangement->rows)
        ->and($plan->arrangement_columns)->toBe($arrangement->columns)
        ->and($plan->seats()->count())->toBe($arrangement->desk_count);
});

test('rejects inactive or room-incompatible seating arrangements as logistics prerequisites', function (): void {
    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $this->settingsAuthority,
        true,
        Institution::current()->examFeaturePolicyVersion(),
    );
    $inactiveArrangement = SeatingArrangement::query()->create([
        'name' => 'Inactive Pub Layout',
        'normalized_name' => 'inactive pub layout',
        'rows' => 2,
        'columns' => 2,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'is_active' => false,
        'lock_version' => 1,
    ]);

    $session = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-AUTH-ARR');
    $attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.arr@example.test', 'Arrangement');
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);

    $session->forceFill([
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'seating_arrangement_id' => $inactiveArrangement->id,
    ])->save();
    assertPublishBlockedEverywhere($session, 'Select an active seating arrangement.');

    $restrictedArrangement = SeatingArrangement::query()->create([
        'name' => 'Restricted Room Layout',
        'normalized_name' => 'restricted room layout',
        'rows' => 2,
        'columns' => 2,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
        'is_active' => true,
        'lock_version' => 1,
    ]);
    $session->forceFill(['seating_arrangement_id' => $restrictedArrangement->id])->save();

    assertPublishBlockedEverywhere($session, "{$restrictedArrangement->name} is not available for {$this->room->name}.");
});

test('rejects a roster that exceeds the explicit physical logistics capacity without partial writes', function (): void {
    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $this->settingsAuthority,
        true,
        Institution::current()->examFeaturePolicyVersion(),
    );
    $smallArrangement = SeatingArrangement::query()->create([
        'name' => 'One By Two Logistics Layout',
        'normalized_name' => 'one by two logistics layout',
        'rows' => 1,
        'columns' => 2,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'is_active' => true,
        'lock_version' => 1,
    ]);
    $session = pubAuthDraftSession(
        $this->guidance,
        $this->cycle,
        $this->room,
        'EX-PUB-AUTH-CAP',
        arrangement: $smallArrangement,
    );
    foreach (['capacity1', 'capacity2', 'capacity3'] as $index => $suffix) {
        $attempt = pubAuthAcceptedApplicant($this->registrar, "pub.auth.{$suffix}@example.test", 'Capacity'.$index);
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    }

    assertPublishBlockedEverywhere($session, 'The seating arrangement does not have enough desks for this roster.');
});

test('rejects stale session version and roster fingerprint without partial logistics writes', function (): void {
    $session = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-AUTH-STALE');
    $attemptOne = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.stale1@example.test', 'StaleOne');
    app(AssignApplicantToExamSession::class)->handle($session, $attemptOne, $this->guidance);

    expect(fn () => app(PublishExamSession::class)->handle($session->fresh(), $this->guidance, 99))
        ->toThrow(RuntimeException::class, 'Stale exam session version.');
    expect(fn () => app(ExamSessionPublishHandler::class)->handle([
        'session_id' => $session->id,
        'expected_version' => 99,
        'execute' => false,
    ]))->toThrow(RuntimeException::class, 'stale_session_version');

    $preflightBefore = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $fingerprintBefore = $preflightBefore['roster_material_fingerprint'];
    expect($fingerprintBefore)->toBeString()->not->toBeEmpty();

    $attemptTwo = pubAuthAcceptedApplicant($this->registrar, 'pub.auth.stale2@example.test', 'StaleTwo');
    app(AssignApplicantToExamSession::class)->handle($session, $attemptTwo, $this->guidance);
    expect($session->fresh()->version)->toBe($session->version);

    expect(fn () => app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        $session->version,
        $fingerprintBefore,
    ))->toThrow(RuntimeException::class, 'Stale exam session roster or logistics state.');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('event_type', 'exam.session_published')->where('payload->exam_session_id', $session->id)->count())->toBe(0);
});

test('publishes the exact returned operational session and blocks its unassigned counterpart', function (): void {
    (new CohortDistributionDemoSeeder)->run();

    $pubReady = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-PUB-READY-'.Str::upper(Str::random(6)));
    $readyAttempt = pubAuthAcceptedApplicant($this->registrar, 'pub.ready.'.Str::lower(Str::random(6)).'@example.test', 'Ready');
    app(AssignApplicantToExamSession::class)->handle($pubReady, $readyAttempt, $this->guidance);

    $dist002 = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-DIST-002-'.Str::upper(Str::random(6)));
    $dist003 = pubAuthDraftSession($this->guidance, $this->cycle, $this->room, 'EX-DIST-003-'.Str::upper(Str::random(6)));
    $dist003Attempt = pubAuthAcceptedApplicant($this->registrar, 'pub.dist003.'.Str::lower(Str::random(6)).'@example.test', 'Distributed');
    app(AssignApplicantToExamSession::class)->handle($dist003, $dist003Attempt, $this->guidance);
    $dist003 = app(PublishExamSession::class)->handle($dist003, $this->guidance, $dist003->version);

    $readyPreflight = app(ExamSessionPublishPreflight::class)->evaluate($pubReady);
    $dist002Preflight = app(ExamSessionPublishPreflight::class)->evaluate($dist002);

    expect($pubReady->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($readyPreflight['ok'])->toBeTrue()
        ->and($readyPreflight['reason'])->toBeNull()
        ->and($dist002->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($dist002Preflight['ok'])->toBeFalse()
        ->and($dist002Preflight['reason'])->toBe('Assign at least one applicant before publishing.')
        ->and($dist003->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $dist003->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $dist003->id)->exists())->toBeFalse()
        ->and(app(CandidatePoolQuery::class)->forExam($this->cycle->id, (int) $this->cycle->institution_id)->count())->toBeGreaterThanOrEqual(10);

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.publish', $dist002), ['expected_version' => $dist002->version])
        ->assertSessionHasErrors('publish');

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.publish', $pubReady), ['expected_version' => $pubReady->version])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($pubReady->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $pubReady->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $pubReady->id)->exists())->toBeFalse()
        ->and($dist002->fresh()->status)->toBe(ExamSession::STATUS_DRAFT);
});
