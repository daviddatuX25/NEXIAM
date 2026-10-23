<?php

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Database\Seeders\AdmissionSupportDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JourneyFixtureSeeder;
use Database\Seeders\OmrDemoSeeder;
use Database\Seeders\PublicIntakeConsentDemoSeeder;

function seedE2Foundation(): void
{
    // RefreshDatabase rolls this transaction back between tests, so the
    // sentinel remains an isolation-safe guard while avoiding duplicate
    // catalog, role, and policy writes inside one test.
    if (Institution::query()->where('code', 'SYN-INST-001')->exists()
        && AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->exists()
        && User::query()->where('email', 'guidance.counselor@nexiam.test')->exists()
        && User::query()->where('email', 'admin.lead@nexiam.test')->exists()) {
        seedE2LegacyProgramHistoryFixtures();

        return;
    }

    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    seedE2LegacyProgramHistoryFixtures();
}

function xiamKeepCurrentSessionCookie(): void
{
    test()->withCredentials()->withCookie(config('session.cookie'), session()->getId());
}

/**
 * Creates retired synthetic references for tests that exercise linked historical records.
 */
function seedE2LegacyProgramHistoryFixtures(): void
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    foreach ([
        ['code' => 'BSN', 'name' => 'Bachelor of Science in Nursing'],
        ['code' => 'BSPSY', 'name' => 'Bachelor of Science in Psychology'],
    ] as $program) {
        Program::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'code' => $program['code']],
            ['name' => $program['name'], 'is_active' => true, 'is_admission_offered' => false],
        );
    }

    Program::query()
        ->where('institution_id', $institution->id)
        ->whereIn('code', ['BSIT', 'BA-Psych'])
        ->update(['is_admission_offered' => true]);

    $historicalHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->first();
    $profile = $historicalHead?->staffProfile;
    if ($profile !== null) {
        $profile->update(['program_codes' => array_values(array_unique([...$profile->program_codes, 'BSIT']))]);
    }
}

function seedE2JourneyFoundation(): void
{
    (new JourneyFixtureSeeder)->run();
}

function seedE2OmrFoundation(): void
{
    (new OmrDemoSeeder)->run();
}

function seedE2AdmissionSupportFoundation(): void
{
    (new AdmissionSupportDemoSeeder)->run();
}

function enableE2AdvancedExamFeaturePolicy(User $actor): void
{
    $institution = Institution::current();
    $policy = app(UpdateInstitutionExamFeaturePolicy::class);
    $policy->updateSeatingAvailability($actor, true, $institution->examFeaturePolicyVersion());
    $institution->refresh();
    $policy->updateAlternateBookletSetsAvailability($actor, true, $institution->examFeaturePolicyVersion());
}

/**
 * Let pipeline tests exercise lifecycle actions without treating the synthetic
 * OMR material seed as an operator-authored artifact in the local demo UI.
 */
function seedE2ApprovedOmrMaterialForPipelineTests(): void
{
    seedE2Foundation();
    seedE2OmrFoundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    enableE2AdvancedExamFeaturePolicy($actor);
    $bookletVersion = ensurePublishReadyExamMaterials($actor)['booklet'];
    $template = $bookletVersion->templateVersion?->template;
    if ($template === null) {
        throw new RuntimeException('The OMR pipeline fixture must be linked to an answer-sheet template.');
    }

    $template->update(['is_synthetic' => false]);
}

function submitSyntheticApplicant(?string $email = null, string $lastName = 'Applicant'): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();

    (new PublicIntakeConsentDemoSeeder)->run();
    $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
        ?? throw new RuntimeException('A designated published public intake privacy policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated intake policy must have a published immutable revision.');

    $intake = new AdmissionIntakeData(
        firstName: 'Test',
        lastName: $lastName,
        birthdate: '2001-01-01',
        sex: 'male',
        contactEmail: $email ?? 'applicant.'.uniqid('', true).'@example.test',
        applicantType: 'new',
        programPreferenceCodes: ['BSIT'],
        grades: [
            'GWA' => '1.75',
            'ENG' => '88.00',
            'FIL' => '90.00',
            'MATH' => '85.00',
            'SCI' => '87.00',
        ],
        middleName: null,
        suffix: null,
        contactPhone: '09171234567',
        lastSchoolEnrolled: 'Sample Senior High School',
        strand: 'STEM',
        addressLine: '123 Sample St',
        city: 'Tagudin',
        province: 'Ilocos Sur',
        zipCode: '2714',
        termsAccepted: true,
        publicPolicyId: (int) $policy->getKey(),
        publicPolicyRevisionId: (int) $revision->getKey(),
        publicPolicyRevisionDigest: $revision->contentDigest(),
    );

    return app(SubmitAdmissionAttempt::class)->handle(
        $institution,
        $cycle,
        $window,
        $intake,
        'test',
    );
}

/**
 * @return array{booklet: OmrQuestionBookletVersion, arrangement: SeatingArrangement}
 */
function ensurePublishReadyExamMaterials(?User $actor = null, ?Room $room = null): array
{
    $actor ??= User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $room ??= Room::query()->where('code', 'RM-101')->firstOrFail();

    $booklet = OmrQuestionBookletVersion::query()
        ->whereNotNull('finalized_at')
        ->whereHas('templateVersion', fn ($q) => $q
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
            ->whereNotNull('native_bundle_json')
            ->whereNotNull('native_bundle_hash')
            ->whereNotNull('template_json_hash'))
        ->with(['templateVersion', 'sets'])
        ->orderByDesc('id')
        ->get()
        ->first(fn (OmrQuestionBookletVersion $candidate): bool => $candidate->sets
            ->filter(fn (OmrBookletSet $set): bool => $set->isFinalized())
            ->pluck('set_code')
            ->intersect(OmrBookletSet::SET_CODES)
            ->count() === count(OmrBookletSet::SET_CODES));

    if ($booklet === null) {
        $sheet = OmrAnswerSheetTemplateVersion::query()
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
            ->whereNotNull('native_bundle_json')
            ->whereNotNull('native_bundle_hash')
            ->with(['areas' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('id')
            ->first();

        if ($sheet === null) {
            seedE2OmrFoundation();
            $sheet = OmrAnswerSheetTemplateVersion::query()
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                ->whereNotNull('native_bundle_json')
                ->whereNotNull('native_bundle_hash')
                ->with(['areas' => fn ($query) => $query->orderBy('id')])
                ->orderByDesc('id')
                ->firstOrFail();
        }
        $draft = app(CreateBookletDraft::class)->handle(
            'E2 Publish Ready Booklet '.uniqid(),
            OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            $actor,
            $sheet,
        );
        $draft = app(SetBookletSetsMode::class)->handle($draft, $actor, true, $draft->lock_version, false);
        $keys = [];
        $globalPosition = 1;
        foreach ($sheet->areas as $area) {
            for ($areaPosition = 1; $areaPosition <= (int) $area->max_items; $areaPosition++) {
                $keys[] = [
                    'aptitude_area_id' => (int) $area->aptitude_area_id,
                    'area_position' => $areaPosition,
                    'global_position' => $globalPosition,
                    'correct_option_code' => ['A', 'B', 'C', 'D'][($globalPosition - 1) % 4],
                ];
                $globalPosition++;
            }
        }
        foreach (OmrBookletSet::SET_CODES as $setCode) {
            app(SaveBookletSetKeys::class)->handle($draft, $setCode, $keys, $actor);
        }
        $booklet = app(FinalizeBookletVersion::class)->handle($draft, $actor)->fresh(['templateVersion', 'sets']);
    }

    $arrangement = SeatingArrangement::query()
        ->where('is_active', true)
        ->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
        ->whereRaw('(rows * columns) <= ?', [(int) $room->capacity])
        ->orderBy('id')
        ->first()
        ?? SeatingArrangement::query()->create([
            'name' => 'E2 Publish Ready Layout '.uniqid(),
            'normalized_name' => 'e2 publish ready layout '.uniqid(),
            'rows' => 2,
            'columns' => min(5, max(1, (int) floor(((int) $room->capacity) / 2))),
            'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
            'is_active' => true,
            'lock_version' => 1,
        ]);

    return [
        'booklet' => $booklet,
        'arrangement' => $arrangement,
    ];
}

function attachPublishReadyExamMaterials(ExamSession $session, ?User $actor = null): ExamSession
{
    $room = $session->room ?? Room::query()->findOrFail($session->room_id);
    $materials = ensurePublishReadyExamMaterials($actor, $room);

    $session->forceFill([
        'booklet_version_id' => $materials['booklet']->id,
        'seating_arrangement_id' => $materials['arrangement']->id,
    ])->save();

    return $session->fresh();
}
