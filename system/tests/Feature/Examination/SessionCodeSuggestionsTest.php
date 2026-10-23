<?php

use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Scheduling\Queries\SessionCodeSuggestions;
use App\Testing\PracticeFixtureSet;

function sessionCodeCycle(Institution $institution, string $code): AcademicCycle
{
    return AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => $code,
        'name' => $code,
        'school_year' => $code,
        'semester' => 'First Semester',
        'opens_on' => '2025-06-01',
        'closes_on' => '2026-05-31',
        'is_active' => false,
    ]);
}

test('session code suggestions advance beyond retained suffix maximum independently by cycle and domain', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $nextCycle = sessionCodeCycle($cycle->institution, 'SYN-CYCLE-NEXT');
    $otherInstitution = Institution::query()->create(['code' => 'SUGGESTION-OTHER', 'name' => 'Other institution']);
    $otherCycle = sessionCodeCycle($otherInstitution, 'SYN-CYCLE-OTHER-INSTITUTION');
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $practiceSet = PracticeFixtureSet::query()->create([
        'label' => 'Naming rehearsal',
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'scenario' => 'exam-scheduling-attendance',
        'purpose' => 'demo',
        'created_by_user_id' => $actor->id,
        'actor_bindings' => ['guidance' => $actor->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);

    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-01', 'status' => ExamSession::STATUS_CANCELLED]);
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-99', 'status' => ExamSession::STATUS_DRAFT, 'practice_fixture_set_id' => $practiceSet->id]);
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-custom']);
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-03']);
    ExamSession::factory()->for($nextCycle, 'academicCycle')->create(['code' => 'Batch-07']);
    ExamSession::factory()->for($otherCycle, 'academicCycle')->create(['code' => 'Batch-20']);
    ConsultationSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Release-04', 'status' => ConsultationSession::STATUS_CANCELLED]);
    ConsultationSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Release-02']);
    ConsultationSession::factory()->for($nextCycle, 'academicCycle')->create(['code' => 'Release-50']);

    $suggestions = app(SessionCodeSuggestions::class);

    expect($suggestions->forCycles('exam', [(int) $cycle->id, (int) $nextCycle->id, (int) $otherCycle->id]))
        ->toBe([$cycle->id => 'Batch-100', $nextCycle->id => 'Batch-08', $otherCycle->id => 'Batch-21'])
        ->and($suggestions->forCycles('release', [(int) $cycle->id, (int) $nextCycle->id]))
        ->toBe([$cycle->id => 'Release-05', $nextCycle->id => 'Release-51'])
        ->and($suggestions->forCycles('exam', [(int) sessionCodeCycle($cycle->institution, 'SYN-CYCLE-EMPTY')->id]))
        ->toBe([AcademicCycle::query()->where('code', 'SYN-CYCLE-EMPTY')->value('id') => 'Batch-01']);
});

test('session code suggestions keep minimum width, handle gaps, and ignore malformed custom suffixes', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-01']);
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-03']);
    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-100x']);

    expect(app(SessionCodeSuggestions::class)->forCycles('exam', [(int) $cycle->id]))
        ->toBe([$cycle->id => 'Batch-04']);

    ExamSession::factory()->for($cycle, 'academicCycle')->create(['code' => 'Batch-999999999999999999999999999999999999999']);

    expect(app(SessionCodeSuggestions::class)->forCycles('exam', [(int) $cycle->id]))
        ->toBe([$cycle->id => 'Batch-1000000000000000000000000000000000000000']);
});
