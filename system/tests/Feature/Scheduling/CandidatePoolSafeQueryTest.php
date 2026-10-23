<?php

declare(strict_types=1);

use App\Ai\Context\InvocationContext;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Scheduling\CandidatePoolQuery;

function candidatePoolContext(User $actor, string $actorType = 'staff', ?int $institutionId = null): InvocationContext
{
    return new InvocationContext(
        actorId: (string) $actor->getKey(),
        actorType: $actorType,
        institutionId: $institutionId ?? $actor->actorScope()->institutionId,
        surfaceProfile: $actorType,
        mode: 'assistant',
        contextEpoch: 'candidate-pool-test',
        purpose: 'assistant_tool',
        correlationId: 'candidate-pool-context-test',
    );
}

function candidatePoolAttempt(AcademicCycle $cycle, string $reference, string $name, string $email, string $phone, string $birthdate): AdmissionAttempt
{
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $cycle->institution_id,
        'normalized_key' => 'ID-'.$reference,
        'first_name' => $name,
        'last_name' => 'PrivateLastName',
        'birthdate' => $birthdate,
        'sex' => 'female',
        'email' => $email,
        'phone' => $phone,
    ]);

    return AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $reference,
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'preferred_program_code' => 'BSCS',
        'strand' => 'STEM',
        'accepted_at' => now()->subDay(),
        'version' => 1,
    ]);
}

test('candidate pool projection returns complete counts and bounded privacy-safe candidates', function (): void {
    $institution = Institution::query()->create([
        'code' => 'CPOOL-TEST',
        'name' => 'Candidate Pool Test Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CPOOL-CYCLE-2026',
        'name' => 'Candidate Pool Cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);
    $room = Room::factory()->for($institution)->create([
        'code' => 'CPOOL-ROOM',
        'capacity' => 7,
        'is_active' => true,
    ]);

    $privateValues = [
        ['AlexPrivateName', 'alex.private@example.test', '+639171234567', '2006-02-03'],
        ['BlairPrivateName', 'blair.private@example.test', '+639171234568', '2006-04-05'],
        ['CaseyPrivateName', 'casey.private@example.test', '+639171234569', '2006-06-07'],
    ];
    foreach ($privateValues as $index => [$name, $email, $phone, $birthdate]) {
        candidatePoolAttempt($cycle, sprintf('APP-2026-%05d', $index + 1), $name, $email, $phone, $birthdate);
    }

    ExamSession::factory()->for($cycle, 'academicCycle')->for($room)->create([
        'code' => 'EX-CPOOL-CAPACITY',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]);

    $projection = app(CandidatePoolQuery::class)->forCandidateListSafe((int) $cycle->id, (int) $institution->id, 2);
    $serialized = json_encode($projection, JSON_THROW_ON_ERROR);

    expect($projection['summary']['academic_cycle_id'])->toBe((int) $cycle->id)
        ->and($projection['summary']['pool_count'])->toBe(3)
        ->and($projection['summary']['available_capacity'])->toBe(7)
        ->and($projection['summary']['returned_count'])->toBe(2)
        ->and($projection['candidates'])->toHaveCount(2)
        ->and($projection['candidates'][0])->toBe([
            'candidate_ref' => 'APP-2026-00001',
            'preferred_program_code' => 'BSCS',
            'strand' => 'STEM',
        ])
        ->and(collect($projection['candidates'])->pluck('candidate_ref')->all())->toBe([
            'APP-2026-00001',
            'APP-2026-00002',
        ])
        ->and(array_map('array_keys', $projection['candidates']))->toBe([
            ['candidate_ref', 'preferred_program_code', 'strand'],
            ['candidate_ref', 'preferred_program_code', 'strand'],
        ])
        ->and($serialized)->not->toContain(
            ...array_merge(
                ['first_name', 'last_name', 'name', 'email', 'phone', 'birthdate'],
                array_merge(...array_map(static fn (array $candidate): array => array_slice($candidate, 0, 4), $privateValues)),
            ),
        );
});

test('candidate pool contextual handler requires active staff and matching institution context', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $handler = app(CandidatePoolQuery::class);

    $nonStaff = $handler->handleWithContext([], $staff, candidatePoolContext($staff, 'applicant'));
    $staff->staffProfile()->update(['status' => StaffProfile::STATUS_INACTIVE]);
    $inactive = $handler->handleWithContext([], $staff->fresh(), candidatePoolContext($staff));
    $staff->staffProfile()->update(['status' => StaffProfile::STATUS_ACTIVE]);

    $foreignInstitutionContext = $handler->handleWithContext(
        [],
        $staff,
        candidatePoolContext($staff, institutionId: (int) $staff->actorScope()->institutionId + 1000),
    );

    expect($nonStaff)->toMatchArray(['page_state' => 'denied', 'reason' => 'staff_actor_required'])
        ->and($inactive)->toMatchArray(['page_state' => 'denied', 'reason' => 'staff_actor_required'])
        ->and($foreignInstitutionContext['page_state'])->toBe('denied')
        ->and($foreignInstitutionContext['reason'])->toBe('institution_context_mismatch');
});

test('candidate pool contextual handler resolves the active academic cycle by default', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeCycle = AcademicCycle::query()
        ->where('institution_id', $staff->actorScope()->institutionId)
        ->where('is_active', true)
        ->firstOrFail();

    $projection = app(CandidatePoolQuery::class)->handleWithContext([], $staff, candidatePoolContext($staff));

    expect($projection['summary']['academic_cycle_id'])->toBe((int) $activeCycle->id)
        ->and($projection['summary']['pool_count'])->toBeInt()
        ->and($projection['summary']['returned_count'])->toBeInt();
});
