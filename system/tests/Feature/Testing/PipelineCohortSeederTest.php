<?php

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Testing\Actions\RegisterPracticeInquiryActors;
use App\Testing\PracticeFixtureSet;
use App\Testing\Seeders\Stages\SeedConductStage;
use App\Testing\Seeders\Stages\SeedConsultationStage;
use App\Testing\Seeders\Stages\SeedEnrollmentStage;
use App\Testing\Seeders\Stages\SeedGradingStage;
use App\Testing\Seeders\Stages\SeedProgramStage;
use App\Testing\Seeders\Stages\SeedReservationStage;
use App\Testing\Seeders\Stages\SeedReviewStage;
use App\Testing\Seeders\Stages\SeedSeatingStage;
use App\Testing\Seeders\Stages\SeedSetupStage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->makePracticeSet = function (): PracticeFixtureSet {
        return PracticeFixtureSet::query()->create([
            'label' => 'Application practice', 'institution_id' => Institution::current()->id,
            'academic_cycle_id' => AcademicCycle::active()->id, 'scenario' => 'application-review',
            'purpose' => 'demo', 'status' => 'active', 'revision' => 1,
            'created_by_user_id' => $this->operator->id,
            'actor_bindings' => ['guidance' => (int) $this->operator->id,
                'registrar' => (int) User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail()->id],
            'material_snapshot' => [], 'credentials' => [],
        ]);
    };
});

test('setup checks resolve the admin lead through its staff profile and remain read only', function (): void {
    $set = ($this->makePracticeSet)();
    $before = [Program::query()->get()->toArray(), ProgramQuota::query()->get()->toArray(), Room::query()->get()->toArray()];
    $beforeCounts = [
        AdmissionAttempt::query()->count(),
        DB::table('users')->count(),
        DB::table('staff_profiles')->count(),
        Program::query()->count(),
        ProgramQuota::query()->count(),
        Room::query()->count(),
        DB::table('application_windows')->count(),
        DB::table('public_policies')->count(),
    ];

    $result = app(SeedSetupStage::class)->forSet($set)->seed();
    $prerequisites = collect($result['prerequisites'])->keyBy('key');

    expect($result['count'])->toBe(0)
        ->and($prerequisites)->not->toBeEmpty()
        ->and($prerequisites['admin_office']['status'])->toBe('available')
        ->and($prerequisites['wayfinding_venues']['status'])->toBeIn(['available', 'missing'])
        ->and($prerequisites['physical_materials']['status'])->toBe('unverified')
        ->and($prerequisites['privacy']['status'])->toBe('missing')
        ->and([
            AdmissionAttempt::query()->count(),
            DB::table('users')->count(),
            DB::table('staff_profiles')->count(),
            Program::query()->count(),
            ProgramQuota::query()->count(),
            Room::query()->count(),
            DB::table('application_windows')->count(),
            DB::table('public_policies')->count(),
        ])->toBe($beforeCounts)
        ->and([Program::query()->get()->toArray(), ProgramQuota::query()->get()->toArray(), Room::query()->get()->toArray()])->toBe($before)
        ->and($set->fresh()->material_snapshot)->toBe([]);
});

test('review creates fresh owned identities without adopting a matching unknown applicant or pinned email', function (): void {
    $unknown = submitSyntheticApplicant('cohort.revw01@nexiam.test', 'Santos')['attempt'];
    $unknown->identity->forceFill([
        'sex' => 'female', 'birthdate' => '2003-01-01',
        'normalized_key' => NormalizedIdentityKey::make('Test', 'Santos', '2003-01-01', 'female'),
    ])->save();
    $identityBefore = $unknown->identity->fresh()->getAttributes();
    $attemptBefore = $unknown->fresh()->getAttributes();
    $challengeBefore = ApplicantAccessChallenge::query()->where('admission_attempt_id', $unknown->id)->get()->toArray();
    config()->set('seed.pipeline_cohorts.stages.review', [[
        'first_name' => 'Test', 'last_name' => 'Santos', 'email' => 'cohort.revw01@nexiam.test',
        'key' => 'REVW-DEM1', 'programs' => ['BSIT'],
    ]]);
    $set = ($this->makePracticeSet)();

    $result = app(SeedReviewStage::class)->forSet($set)->seed();
    $practice = $result['attempts'][0];
    $credentials = $set->fresh()->credentials[(string) $practice->applicant_identity_id];

    expect($practice->id)->not->toBe($unknown->id)
        ->and($practice->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($practice->practice_fixture_set_id)->toBe($set->id)
        ->and($practice->identity->practice_fixture_set_id)->toBe($set->id)
        ->and($practice->identity->email)->not->toBe('cohort.revw01@nexiam.test')
        ->and(ApplicantAccessKey::isValidShape($credentials['access_key']))->toBeTrue()
        ->and($credentials['access_key'])->not->toBe('REVW-DEM1')
        ->and($unknown->identity->fresh()->getAttributes())->toBe($identityBefore)
        ->and($unknown->fresh()->getAttributes())->toBe($attemptBefore)
        ->and(ApplicantAccessChallenge::query()->where('admission_attempt_id', $unknown->id)->get()->toArray())->toBe($challengeBefore)
        ->and(DB::table('practice_fixture_sets')->where('id', $set->id)->value('credentials'))->not->toContain($credentials['access_key']);
});

test('stage replay preserves outcomes and a fresh set has distinct secure credentials', function (): void {
    $set = ($this->makePracticeSet)();
    $first = app(SeedReviewStage::class)->forSet($set)->seed();
    $attempt = $first['attempts'][0];
    $attempt->update(['status' => AdmissionAttempt::STATUS_DISMISSED]);
    $key = $set->fresh()->credentials[(string) $attempt->applicant_identity_id]['access_key'];

    $replayed = app(SeedReviewStage::class)->forSet($set)->seed();
    $secondSet = ($this->makePracticeSet)();
    $second = app(SeedReviewStage::class)->forSet($secondSet)->seed();

    expect(array_column($replayed['attempts'], 'id'))->toBe(array_column($first['attempts'], 'id'))
        ->and($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_DISMISSED)
        ->and($set->fresh()->credentials[(string) $attempt->applicant_identity_id]['access_key'])->toBe($key)
        ->and($second['attempts'][0]->id)->not->toBe($attempt->id)
        ->and($secondSet->fresh()->credentials[(string) $second['attempts'][0]->applicant_identity_id]['access_key'])->not->toBe($key);
});

test('reservation uses actual immutable room and material dependencies and leaves accepted cases unassigned', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $set = ($this->makePracticeSet)();
    $before = Room::query()->get()->toArray();
    $quotas = ProgramQuota::query()->get()->toArray();

    $result = app(SeedReservationStage::class)->forSet($set)->seed();
    $session = $result['session'];

    expect($session->practice_fixture_set_id)->toBe($set->id)
        ->and($session->isOpenForReservation())->toBeTrue()
        ->and($set->fresh()->material_snapshot['session_dependencies'][(string) $session->id]['room_id'])->toBe($session->room_id)
        ->and($set->fresh()->material_snapshot['booklet_version_id'])->toBe($session->booklet_version_id)
        ->and(Room::query()->get()->toArray())->toBe($before)
        ->and(ProgramQuota::query()->get()->toArray())->toBe($quotas);
    foreach ($result['attempts'] as $attempt) {
        expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
            ->and($attempt->examAssignments()->count())->toBe(0);
    }
});

test('missing exam material blocks all dependent case writes', function (): void {
    $set = ($this->makePracticeSet)();
    $before = AdmissionAttempt::query()->count();

    expect(fn () => app(SeedReservationStage::class)->forSet($set)->seed())->toThrow(RuntimeException::class);
    expect(AdmissionAttempt::query()->count())->toBe($before)
        ->and($set->fresh()->credentials)->toBe([]);
});

test('missing document prerequisites never manufacture print evidence', function (): void {
    $set = ($this->makePracticeSet)();
    $before = AdmissionAttempt::query()->count();
    $receipts = ResultDocumentPrintReceipt::query()->count();

    expect(fn () => app(SeedConsultationStage::class)->forSet($set)->seed())->toThrow(RuntimeException::class);
    expect(AdmissionAttempt::query()->count())->toBe($before)
        ->and(ResultDocumentPrintReceipt::query()->count())->toBe($receipts);
});

test('retired sets and builders without explicit context fail closed', function (): void {
    $set = ($this->makePracticeSet)();
    $set->update(['status' => 'retired', 'retired_at' => now()]);
    $count = AdmissionAttempt::query()->count();

    expect(fn () => app(SeedReviewStage::class)->seed())->toThrow(RuntimeException::class)
        ->and(fn () => app(SeedReviewStage::class)->forSet($set)->seed())->toThrow(RuntimeException::class);
    expect(AdmissionAttempt::query()->count())->toBe($count);
});

test('stage and pipeline menus are nonmutating and stage writes require an explicit operator', function (): void {
    $count = AdmissionAttempt::query()->count();
    expect(Artisan::call('demo:stage'))->toBe(0)
        ->and(Artisan::call('demo:seed-pipeline'))->toBe(0)
        ->and(Artisan::call('demo:stage', ['stage' => 'review']))->toBe(1)
        ->and(Artisan::call('demo:stage', ['stage' => 'invalid-stage']))->toBe(1)
        ->and(AdmissionAttempt::query()->count())->toBe($count);
});

test('later builders use real domain states without manufacturing paper delivery or enrollment history', function (string $builder, string $stage): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $set = ($this->makePracticeSet)();
    $printCount = ResultDocumentPrintReceipt::query()->count();

    $result = app($builder)->forSet($set)->seed();

    expect($result['stage'])->toBe($stage)
        ->and($result['count'])->toBe(3)
        ->and($result['session']->practice_fixture_set_id)->toBe($set->id)
        ->and(ResultDocumentPrintReceipt::query()->count())->toBe($printCount);
    foreach ($result['attempts'] as $attempt) {
        expect($attempt->practice_fixture_set_id)->toBe($set->id)
            ->and($attempt->identity->practice_fixture_set_id)->toBe($set->id);
    }
    if ($stage === 'seating') {
        expect($result['session']->status)->toBe(ExamSession::STATUS_DRAFT)
            ->and($result['session']->assignments()->count())->toBe(0);
    } elseif ($stage === 'conduct') {
        expect($result['session']->status)->toBe(ExamSession::STATUS_PUBLISHED)
            ->and($result['session']->assignments()->count())->toBe(3)
            ->and($result['session']->assignments()->whereHas('attendanceRecord', fn ($query) => $query->where('outcome', 'absent'))->count())->toBe(1)
            ->and(collect($result['attempts'])->map(fn (AdmissionAttempt $attempt) => $attempt->identity->first_name)->all())->toBe(['Carlos', 'Clara', 'Caleb']);
    } elseif ($stage === 'grading') {
        expect($result['session']->status)->toBe(ExamSession::STATUS_COMPLETED)
            ->and($result['grading']->status)->toBe(GradingSession::STATUS_OPEN)
            ->and($result['grading']->scoreRecords()->distinct()->count('admission_attempt_id'))->toBe(1);
    } else {
        expect($result['grading']->status)->toBe(GradingSession::STATUS_FINALIZED)
            ->and($result['documents'])->toHaveCount(3)
            ->and(collect($result['gates'])->firstWhere('key', 'physical_print')['status'])->toBe('missing');
        foreach ($result['attempts'] as $attempt) {
            expect($attempt->consultationDecisions()->count())->toBe(0)
                ->and($attempt->programSelections()->count())->toBe(0);
        }
    }
})->with([
    [SeedSeatingStage::class, 'seating'],
    [SeedConductStage::class, 'conduct'],
    [SeedGradingStage::class, 'grading'],
    [SeedConsultationStage::class, 'consultation'],
    [SeedProgramStage::class, 'program'],
    [SeedEnrollmentStage::class, 'enrollment'],
]);

test('research registrar has a distinct ordinary correction-request case and pending review cases', function (): void {
    $set = ($this->makePracticeSet)();
    $set->update(['purpose' => 'research', 'scenario' => 'registrar']);
    $result = app(SeedReviewStage::class)->forSet($set)->seed();

    expect(collect($result['attempts'])->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION))->toHaveCount(1)
        ->and(collect($result['attempts'])->where('status', AdmissionAttempt::STATUS_PENDING))->toHaveCount(2)
        ->and($result['attempts'][0]->correction_notes)->not->toBeEmpty();
});

test('fresh student bootstrap creates only its own applicant authority and never restores a revoked grant on replay', function (): void {
    $set = ($this->makePracticeSet)();
    $set->update(['purpose' => 'research', 'scenario' => 'student']);
    foreach (['guidance', 'registrar'] as $role) {
        $staff = User::query()->findOrFail($set->actor_bindings[$role]);
        $staff->givePermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE);
        ApplicantOfficeInquiryDemoActor::query()->firstOrCreate([
            'user_id' => $staff->id, 'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE,
        ], ['institution_id' => $set->institution_id, 'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_STAFF]);
    }
    $attempt = app(SeedReviewStage::class)->forSet($set)->seed()['attempts'][0];
    $credentials = $set->fresh()->credentials[(string) $attempt->applicant_identity_id];
    $registration = app(RegisterPracticeInquiryActors::class);

    $registration->bootstrap($set->fresh(), $attempt->identity, $credentials['access_key'], $credentials['birthdate']);
    $user = $attempt->identity->fresh()->applicantAccount->user;

    expect($user->email)->toBe($attempt->identity->email)
        ->and(app(CapabilityAuthorizer::class)->decideFresh($user, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed)->toBeTrue();
    $user->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    expect(fn () => $registration->bootstrap($set->fresh(), $attempt->identity->fresh(), $credentials['access_key'], $credentials['birthdate']))
        ->toThrow(ValidationException::class);
    expect(app(CapabilityAuthorizer::class)->decideFresh($user, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed)->toBeFalse();
});

test('student bootstrap never adopts or grants an existing account matching a new practice email', function (): void {
    $set = ($this->makePracticeSet)();
    $set->update(['purpose' => 'research', 'scenario' => 'student']);
    foreach (['guidance', 'registrar'] as $role) {
        $staff = User::query()->findOrFail($set->actor_bindings[$role]);
        $staff->givePermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE);
        ApplicantOfficeInquiryDemoActor::query()->firstOrCreate([
            'user_id' => $staff->id, 'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE,
        ], ['institution_id' => $set->institution_id, 'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_STAFF]);
    }
    $attempt = app(SeedReviewStage::class)->forSet($set)->seed()['attempts'][0];
    $credentials = $set->fresh()->credentials[(string) $attempt->applicant_identity_id];
    $unknown = User::factory()->create(['email' => $attempt->identity->email]);
    $before = $unknown->fresh()->getAttributes();

    expect(fn () => app(RegisterPracticeInquiryActors::class)->bootstrap(
        $set->fresh(), $attempt->identity, $credentials['access_key'], $credentials['birthdate'],
    ))->toThrow(ValidationException::class);
    expect($unknown->fresh()->getAttributes())->toBe($before)
        ->and($attempt->identity->fresh()->applicantAccount)->toBeNull()
        ->and(app(CapabilityAuthorizer::class)->decideFresh($unknown, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed)->toBeFalse();
});
