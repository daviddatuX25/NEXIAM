<?php

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticePreparationOperation;
use App\Testing\PracticeWorkspace;

beforeEach(function (): void {
    seedE2Foundation();
    $this->operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::active();
    $this->room = Room::query()->where('institution_id', $cycle->institution_id)->active()->examRooms()->firstOrFail();
    $this->set = PracticeFixtureSet::query()->create([
        'label' => 'Timeline prerequisite practice', 'institution_id' => $cycle->institution_id, 'academic_cycle_id' => $cycle->id,
        'purpose' => 'demo', 'scenario' => 'exam-scheduling-attendance', 'created_by_user_id' => $this->operator->id,
        'actor_bindings' => ['guidance' => (int) $this->operator->id], 'material_snapshot' => [], 'credentials' => [],
    ]);
    $this->session = app(PracticeWorkspace::class)->run($this->set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id, 'room_id' => $this->room->id, 'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->addDays(5), 'effective_end_at' => now()->addDays(5)->addHours(2),
    ]));
    $this->timeInput = ['purpose' => 'demo', 'scenario' => 'exam-scheduling-attendance', 'action' => 'time',
        'set_id' => $this->set->id, 'session_id' => $this->session->id, 'preset' => '1h-reminder'];
    $this->actingAs($this->operator);
});

test('timeline preview disables an unsuitable inactive or insufficient room without changing the session', function (string $condition): void {
    $before = $this->session->fresh()->getAttributes();
    $this->room->update($condition === 'inactive' ? ['is_active' => false] : ['capacity' => 0]);
    $preview = $this->postJson('/demo/preparation/preview', $this->timeInput)->assertOk()->assertJsonPath('can_confirm', false);
    expect(collect($preview->json('prerequisites'))->firstWhere('key', 'timeline-room')['status'])->toBe('missing');
    $this->postJson($preview->json('operation.confirm_url'), [])->assertUnprocessable();
    expect($this->session->fresh()->getAttributes())->toBe($before)
        ->and(PracticePreparationOperation::query()->findOrFail($preview->json('operation.id'))->status)->toBe('preview');
})->with(['inactive', 'capacity']);

test('timeline preview checks the exact frozen target against same-set neighbours not operational sessions', function (): void {
    $before = $this->session->fresh()->getAttributes();
    $operational = ExamSession::factory()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id, 'room_id' => $this->room->id, 'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->addHour(), 'effective_end_at' => now()->addHours(3),
    ]);
    $operationalBefore = $operational->fresh()->getAttributes();
    $this->postJson('/demo/preparation/preview', $this->timeInput)->assertOk()->assertJsonPath('can_confirm', true);
    $neighbour = app(PracticeWorkspace::class)->run($this->set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id, 'room_id' => $this->room->id, 'status' => ExamSession::STATUS_DRAFT,
        'starts_at' => now()->addHour(), 'effective_end_at' => now()->addHours(3),
    ]));
    $preview = $this->postJson('/demo/preparation/preview', $this->timeInput)->assertOk()->assertJsonPath('can_confirm', false);
    expect(collect($preview->json('prerequisites'))->firstWhere('key', 'timeline-no_room_collision')['status'])->toBe('missing');
    $neighbourBefore = $neighbour->fresh()->getAttributes();
    $this->postJson($preview->json('operation.confirm_url'), [])->assertUnprocessable();
    expect($this->session->fresh()->getAttributes())->toBe($before)->and($neighbour->fresh()->getAttributes())->toBe($neighbourBefore)
        ->and($operational->fresh()->getAttributes())->toBe($operationalBefore);
});

test('room or neighbour changes after a valid timeline preview conflict without changing the selected timeline', function (string $change): void {
    $neighbour = app(PracticeWorkspace::class)->run($this->set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id, 'room_id' => $this->room->id, 'status' => ExamSession::STATUS_DRAFT,
        'starts_at' => now()->addDays(7), 'effective_end_at' => now()->addDays(7)->addHours(2),
    ]));
    $before = $this->session->fresh()->getAttributes();
    $preview = $this->postJson('/demo/preparation/preview', $this->timeInput)->assertOk()->assertJsonPath('can_confirm', true);
    if ($change === 'room') {
        $this->room->update(['capacity' => $this->room->capacity + 1]);
    } else {
        app(PracticeWorkspace::class)->run($this->set, fn () => $neighbour->update(['version' => $neighbour->version + 1]));
    }
    $this->postJson($preview->json('operation.confirm_url'), [])->assertConflict();
    expect($this->session->fresh()->getAttributes())->toBe($before);
})->with(['room', 'neighbour']);
