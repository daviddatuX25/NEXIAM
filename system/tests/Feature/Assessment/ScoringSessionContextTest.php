<?php

use App\Assessment\Support\EnsureGradingSessionForCompletedExam;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_MANUAL, 'web');
    $this->guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL);
});

function createScoringExamSession(string $status = ExamSession::STATUS_COMPLETED, ?AcademicCycle $cycle = null): ExamSession
{
    static $sequence = 0;
    $sequence++;

    return ExamSession::query()->create([
        'academic_cycle_id' => ($cycle ?? test()->cycle)->id,
        'room_id' => test()->room->id,
        'code' => 'SCORING-CONTEXT-'.$sequence,
        'status' => $status,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'version' => 1,
    ]);
}

test('completed exam grading initialization is idempotent', function (): void {
    $examSession = createScoringExamSession();
    $gradingSession = app(EnsureGradingSessionForCompletedExam::class)->handle($examSession, $this->guidance, 'close');

    $result = app(EnsureGradingSessionForCompletedExam::class)->handle($examSession, $this->guidance, 'close');

    expect($result->is($gradingSession))->toBeTrue()
        ->and(GradingSession::query()->where('exam_session_id', $examSession->id)->count())->toBe(1);
});

test('grading initialization rejects an exam session that is not completed', function (): void {
    $examSession = createScoringExamSession(ExamSession::STATUS_DRAFT);

    expect(fn () => app(EnsureGradingSessionForCompletedExam::class)->handle($examSession, $this->guidance, 'close'))
        ->toThrow(RuntimeException::class, 'A grading session can only be initialized for a completed exam session.');
});

test('grading initialization rejects an invalid source', function (): void {
    $examSession = createScoringExamSession();

    expect(fn () => app(EnsureGradingSessionForCompletedExam::class)->handle($examSession, $this->guidance, 'browser'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported grading session initialization source.');
});

test('scoring context resolves the grading session through its exam foreign key', function (): void {
    $examSession = createScoringExamSession();
    $gradingSession = app(EnsureGradingSessionForCompletedExam::class)->handle($examSession, $this->guidance, 'close');

    $resolved = app(ScoringSessionContextResolver::class)->resolveForRead($examSession, $this->guidance);

    expect($resolved->getKey())->toBe($gradingSession->getKey())
        ->and($resolved->exam_session_id)->toBe($examSession->getKey());
});

test('scoring context resolves colliding exam and grading identifiers by foreign key', function (): void {
    $exam101 = createScoringExamSession();
    $exam700 = createScoringExamSession();
    $examTemporary = createScoringExamSession();
    $ensure = app(EnsureGradingSessionForCompletedExam::class);
    $grading700 = $ensure->handle($exam101, $this->guidance, 'close');
    $grading101 = $ensure->handle($exam700, $this->guidance, 'close');

    // Rotate foreign keys using an empty temporary session to establish swapped keys
    $grading700->update(['exam_session_id' => $examTemporary->id]);
    $grading101->update(['exam_session_id' => $exam101->id]);
    $grading700->update(['exam_session_id' => $exam700->id]);

    $resolver = app(ScoringSessionContextResolver::class);

    expect($resolver->resolveForRead($exam101->fresh(), $this->guidance)->getKey())->toBe($grading101->id)
        ->and($resolver->resolveForRead($exam700->fresh(), $this->guidance)->getKey())->toBe($grading700->id);
});

test('scoring context requires the requested capability for writes', function (): void {
    $examSession = createScoringExamSession();
    $resolver = app(ScoringSessionContextResolver::class);

    expect(fn () => $resolver->requireForWrite(
        $examSession,
        $this->guidance,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ))->not->toThrow(AuthorizationException::class)
        ->and(fn () => $resolver->requireForWrite(
            $examSession,
            $this->clerk,
            CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        ))->toThrow(AuthorizationException::class);
});

test('scoring context hides a foreign institution exam session', function (): void {
    $foreignInstitution = Institution::query()->create([
        'code' => 'SCORING-FOREIGN-'.uniqid(),
        'name' => 'Foreign scoring institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'SCORING-FOREIGN-CYCLE-'.uniqid(),
        'name' => 'Foreign scoring cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => now()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $examSession = createScoringExamSession(cycle: $foreignCycle);

    expect(fn () => app(ScoringSessionContextResolver::class)->resolveForRead($examSession, $this->guidance))
        ->toThrow(NotFoundHttpException::class);
});
