<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Queries\CaseConversationStartQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

/** @return array{attempt:AdmissionAttempt, actor:User, program:Program, cycle:AcademicCycle} */
function caseStartFixture(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'start-pool-'.md5($email));
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'start-selection-'.md5($email));
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $actor->staffProfile()->firstOrFail()->update(['program_codes' => [$program->code]]);

    return ['attempt' => $attempt->fresh(), 'actor' => $actor->fresh(), 'program' => $program, 'cycle' => $attempt->academicCycle];
}

test('Program Head context and discovery return only authorized program and name-reference applicant fields', function (): void {
    seedE2Foundation();
    $fixture = caseStartFixture($this, 'start-query-owner@example.test');
    $query = app(CaseConversationStartQuery::class);

    $context = $query->context($fixture['actor']);
    expect($context['programs'])->toBe([['code' => 'BSN', 'name' => $fixture['program']->name]])
        ->and($context['cycle_code'])->toBe($fixture['cycle']->code)
        ->and($context['lookup_url'])->toBe(route('staff.inquiries.case-applicants', [], false));

    $result = $query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, mb_strtolower($fixture['attempt']->reference_code));
    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['id'])->toBe((string) $fixture['attempt']->id)
        ->and($result['data'][0]['reference_code'])->toBe($fixture['attempt']->reference_code)
        ->and($result['data'][0]['cycle_code'])->toBe($fixture['cycle']->code)
        ->and(array_keys($result['data'][0]))->toBe(['id', 'name', 'reference_code', 'cycle_code'])
        ->and($result['has_more'])->toBeFalse();
    expect(fn () => $query->applicants($fixture['actor'], 'BSIT', $fixture['cycle']->code, null))
        ->toThrow(HttpException::class);
});

test('owned Dean context enumerates several programs but foreign program or stale year is opaque', function (): void {
    seedE2Foundation();
    $fixture = caseStartFixture($this, 'start-query-dean@example.test');
    $institutionId = (int) $fixture['program']->institution_id;
    $second = Program::query()->where('code', 'BSIT')->firstOrFail();
    $department = Department::query()->create([
        'institution_id' => $institutionId,
        'code' => 'START-DEAN-'.substr(md5((string) $fixture['actor']->id), 0, 8),
        'name' => 'Synthetic Start Dean Department',
        'dean_user_id' => $fixture['actor']->id,
        'is_active' => true,
    ]);
    $fixture['program']->update(['department_id' => $department->id]);
    $second->update(['department_id' => $department->id]);
    $fixture['actor']->staffProfile()->firstOrFail()->update(['department_id' => $department->id, 'program_codes' => []]);
    $fixture['actor']->givePermissionTo([
        CapabilityCatalog::PROGRAM_HEAD_VIEW,
        CapabilityCatalog::PROGRAM_HEAD_MESSAGE,
        CapabilityCatalog::RECORD_THREAD_PARTICIPATE,
    ]);

    $query = app(CaseConversationStartQuery::class);
    $context = $query->context($fixture['actor']->fresh());
    expect(collect($context['programs'])->pluck('code')->all())->toContain('BSN', 'BSIT');

    expect(fn () => $query->applicants($fixture['actor'], 'CAS', $fixture['cycle']->code, null))
        ->toThrow(HttpException::class);
    expect(fn () => $query->applicants($fixture['actor'], 'BSN', 'STALE-CYCLE', null))
        ->toThrow(HttpException::class);
});

test('discovery loses applicants when current selection or eligibility is removed', function (): void {
    seedE2Foundation();
    $fixture = caseStartFixture($this, 'start-query-loss@example.test');
    $query = app(CaseConversationStartQuery::class);

    expect($query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, 'Messaging')['data'])->toHaveCount(1);

    $fixture['attempt']->update(['status' => AdmissionAttempt::STATUS_PENDING]);
    expect($query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, null)['data'])->toBe([]);

    $fixture['attempt']->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    ResultRelease::query()->where('admission_attempt_id', $fixture['attempt']->id)->update(['is_current' => false]);
    expect($query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, null)['data'])->toBe([]);

    ResultRelease::query()->where('admission_attempt_id', $fixture['attempt']->id)->update(['is_current' => true]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $guidance->id,
        'reason' => 'Case start query enrollment cutoff fixture',
        'version' => 1,
    ]);
    expect($query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, null)['data'])->toBe([]);

    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_REVERSE_ENROLLED,
        'stamped_by_user_id' => $guidance->id,
        'reason' => 'Case start query reversal fixture',
        'version' => 2,
    ]);
    expect($query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, null)['data'])->toHaveCount(1);
});

test('applicant lookup rechecks program access after the starter was displayed', function (): void {
    seedE2Foundation();
    $fixture = caseStartFixture($this, 'start-query-revoked@example.test');
    $url = route('staff.inquiries.case-applicants', [
        'program_code' => 'BSN',
        'cycle_code' => $fixture['cycle']->code,
    ]);
    actingAsMessagingActor($this, $fixture['actor'])->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $fixture['attempt']->id);

    $fixture['actor']->staffProfile()->update(['program_codes' => []]);
    actingAsMessagingActor($this, $fixture['actor'])->getJson($url)
        ->assertNotFound()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonMissing(['reference_code' => $fixture['attempt']->reference_code]);
});

test('Messages discovery includes an unissued targeted referral only while its program review remains open', function (): void {
    seedE2Foundation();
    $fixture = caseStartFixture($this, 'start-query-unissued-referral@example.test');
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $followUp = app(OpenProgramFollowUp::class)->handle(
        $fixture['attempt'],
        $guidance,
        'BSN',
        'Review the referred academic case.',
        'start-query-unissued-referral',
    );
    ResultRelease::query()->where('admission_attempt_id', $fixture['attempt']->id)->update(['is_current' => false]);
    $query = app(CaseConversationStartQuery::class);
    $lookup = fn (): array => $query->applicants($fixture['actor'], 'BSN', $fixture['cycle']->code, $fixture['attempt']->reference_code)['data'];

    expect(array_column($lookup(), 'id'))->toBe([(string) $fixture['attempt']->id]);
    $followUp->update(['status' => ProgramFollowUp::STATUS_APPEARANCE_REQUESTED]);
    expect(array_column($lookup(), 'id'))->toBe([(string) $fixture['attempt']->id]);
    $followUp->update(['status' => ProgramFollowUp::STATUS_CLEARED_BY_PH, 'resolved_at' => now()]);
    expect($lookup())->toBe([]);
});
