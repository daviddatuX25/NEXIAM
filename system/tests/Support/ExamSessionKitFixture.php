<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;

require_once __DIR__.'/TestKitHelpers.php';

/** @return array{session:ExamSession,assignment_id:int,kit_version:TestKitVersion,receipt_id:null} */
function examSessionKitFixture(User $actor, ?ExamSession $session = null): array
{
    $institution = Institution::current();
    $materials = testKitAcceptanceFixture($actor, $institution);
    $kitVersion = $materials['version']->fresh([
        'templateVersion',
        'bookletVersion',
        'bookletSet',
        'testInstrumentVersion',
    ]);
    $profile = $materials['profile'];
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    if ($session === null) {
        $session = app(CreateExamSession::class)->handle(
            $cycle,
            $room,
            'KIT-PUBLISH-'.Str::upper(Str::random(8)),
            now()->addDays(2)->toDateTimeString(),
            now()->addDays(2)->addHours(2)->toDateTimeString(),
            $actor,
            ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
            $kitVersion->bookletVersion,
            null,
            null,
            $profile,
            (int) $kitVersion->id,
        );
    } else {
        $session->update([
            'booklet_version_id' => $kitVersion->booklet_version_id,
            'test_instrument_version_id' => $kitVersion->test_instrument_version_id,
            'test_kit_version_id' => $kitVersion->id,
        ]);
    }

    if ($session->assignments()->doesntExist()) {
        $attempt = submitSyntheticApplicant('kit-publish-'.Str::lower(Str::random(8)).'@example.test', 'Kit'.Str::upper(Str::random(6)));
        $acceptedAttempt = app(AcceptAdmissionAttempt::class)->handle($attempt['attempt'], $actor, 1);
        $assignment = app(AssignApplicantToExamSession::class)->handle($session, $acceptedAttempt, $actor);
    } else {
        $assignment = $session->assignments()->first();
    }

    return [
        'session' => $session->fresh(),
        'assignment_id' => (int) $assignment->id,
        'kit_version' => $kitVersion,
        'receipt_id' => null,
    ];
}

/** @return array{session:ExamSession,assignment_id:int} */
function examSessionNoKitFixture(User $actor): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'NO-KIT-PUBLISH-'.Str::upper(Str::random(8)),
        now()->addDays(2)->toDateTimeString(),
        now()->addDays(2)->addHours(2)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $submitted = submitSyntheticApplicant(
        'no-kit-publish-'.Str::lower(Str::random(8)).'@example.test',
        'No Kit '.Str::upper(Str::random(6)),
    );
    $acceptedAttempt = app(AcceptAdmissionAttempt::class)->handle($submitted['attempt'], $actor, 1);
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $acceptedAttempt, $actor);

    return ['session' => $session->fresh(), 'assignment_id' => (int) $assignment->id];
}
