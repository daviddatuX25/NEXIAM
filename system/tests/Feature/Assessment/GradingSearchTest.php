<?php

use App\Assessment\Queries\GradingSessionListQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

function authenticatedGradingSearchStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
    ]);
}

function buildGradingSearchFixture(): array
{
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $institution = $cycle->institution;
    $session = ExamSession::factory()->for($cycle, 'academicCycle')->for($room)->create([
        'practice_fixture_set_id' => null,
        'code' => 'EX-GRAD-'.uniqid(),
        'status' => ExamSession::STATUS_COMPLETED,
    ]);
    expect($session->academic_cycle_id)->toBe($cycle->id)
        ->and($session->academicCycle->institution_id)->toBe($institution->id);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
        'opened_by_user_id' => $guidance->id,
        'version' => 1,
    ]);

    return compact('guidance', 'institution', 'cycle', 'session', 'grading');
}

test('grading hub validates and returns the committed server search', function (): void {
    $fixture = buildGradingSearchFixture();
    $guidance = $fixture['guidance'];
    $fixture['session']->update(['code' => 'GRADING-CANONICAL-CODE']);

    authenticatedGradingSearchStaff($guidance)
        ->get(route('grading.index', ['search' => '  CANONICAL-CODE  ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessment/Grading')
            ->where('filters.search', 'CANONICAL-CODE')
            ->has('sessions', 1)
            ->where('sessions.0.exam_code', 'GRADING-CANONICAL-CODE')
            ->where('sessions.0.room_name', $fixture['session']->room()->value('name')));

    authenticatedGradingSearchStaff($guidance)
        ->get(route('grading.index', ['search' => ['invalid']]))
        ->assertSessionHasErrors('search');

    authenticatedGradingSearchStaff($guidance)
        ->get(route('grading.index', ['search' => str_repeat('x', 101)]))
        ->assertSessionHasErrors('search');
});

test('grading search matches server-projected exam code and room before the session limit', function (): void {
    $older = buildGradingSearchFixture();
    $newer = buildGradingSearchFixture();
    $older['session']->update(['code' => 'NEEDLE-EXAM-CODE']);
    $newer['session']->update(['code' => 'UNRELATED-EXAM-CODE']);
    $older['grading']->update(['opened_at' => now()->subDays(2)]);
    $newer['grading']->update(['opened_at' => now()->subDay()]);

    $institutionId = (int) $older['institution']->id;
    $payload = app(GradingSessionListQuery::class)->forInertia($institutionId, 1, 'NEEDLE-EXAM-CODE');

    expect($payload['sessions'])->toHaveCount(1)
        ->and($payload['sessions'][0]['exam_code'])->toBe('NEEDLE-EXAM-CODE')
        ->and($payload['filters']['search'])->toBe('NEEDLE-EXAM-CODE');

    $roomName = 'Room-'.uniqid();
    $room = Room::query()->create([
        'institution_id' => $older['session']->academicCycle->institution_id,
        'code' => 'ROOM-'.uniqid(),
        'name' => $roomName,
        'capacity' => 20,
        'is_active' => true,
    ]);
    $older['session']->update(['room_id' => $room->id]);
    $roomPayload = app(GradingSessionListQuery::class)->forInertia($institutionId, 1, $roomName);

    expect($roomPayload['sessions'])->toHaveCount(1)
        ->and($roomPayload['sessions'][0]['exam_code'])->toBe('NEEDLE-EXAM-CODE')
        ->and($roomPayload['sessions'][0]['room_name'])->toBe($roomName);
});

test('grading queue defaults to unfinished scoring and keeps finalized work explicitly recoverable', function (): void {
    $open = buildGradingSearchFixture();
    $finalized = buildGradingSearchFixture();
    $finalized['grading']->update(['status' => GradingSession::STATUS_FINALIZED]);
    $missing = buildGradingSearchFixture();
    $missing['grading']->delete();

    $query = app(GradingSessionListQuery::class);
    $default = $query->forInertia((int) $open['institution']->id);
    $all = $query->forInertia((int) $open['institution']->id, status: 'all');
    $repair = $query->forInertia((int) $open['institution']->id, status: 'repair_required');

    $defaultIds = collect($default['sessions'])->pluck('exam_session_id');
    expect($defaultIds)->toContain($open['session']->id, $missing['session']->id)
        ->and($defaultIds)->not->toContain($finalized['session']->id)
        ->and(collect($all['sessions'])->pluck('exam_session_id'))->toContain($finalized['session']->id)
        ->and(collect($repair['sessions'])->pluck('exam_session_id'))->toContain($missing['session']->id);
});

test('score import affordance follows its separate capability for manual scoring staff', function (bool $canImport): void {
    $fixture = buildGradingSearchFixture();
    $guidance = $fixture['guidance'];
    if (! $canImport) {
        $guidance->syncRoles([]);
        $guidance->syncPermissions([CapabilityCatalog::GUIDANCE_SCORING_MANUAL]);
    }

    authenticatedGradingSearchStaff($guidance)
        ->get(route('scoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessment/Grading')
            ->where('can_import_scores', $canImport));
})->with([true, false]);
