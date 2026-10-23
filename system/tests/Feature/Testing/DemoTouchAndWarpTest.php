<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Testing\Actions\WarpDemoSessionTimeline;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

function warpOwnedTimeline(ExamSession $session, string $preset, User $operator, PracticeFixtureSet $set, ?CarbonImmutable $reference = null): array
{
    return app(PracticeWorkspace::class)->run($set, fn (): array => app(WarpDemoSessionTimeline::class)->handle($session, $preset, $operator, $set, $reference));
}

beforeEach(function (): void {
    seedE2Foundation();
    $this->operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->set = PracticeFixtureSet::query()->create([
        'label' => 'Timing practice', 'institution_id' => Institution::current()->id,
        'academic_cycle_id' => AcademicCycle::active()->id, 'scenario' => 'exam-scheduling-attendance',
        'purpose' => 'demo', 'status' => 'active', 'revision' => 1,
        'created_by_user_id' => $this->operator->id, 'actor_bindings' => ['guidance' => (int) $this->operator->id],
        'credentials' => [], 'material_snapshot' => [],
    ]);
    $this->session = app(PracticeWorkspace::class)->run($this->set, fn () => ExamSession::query()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id,
        'room_id' => Room::query()->where('institution_id', $this->set->institution_id)->where('is_active', true)->firstOrFail()->id,
        'code' => 'OWNED-TIMING-'.substr($this->set->id, 0, 8),
        'status' => ExamSession::STATUS_DRAFT, 'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(8), 'effective_end_at' => now()->addDays(8)->addHours(2),
        'version' => 1,
    ]));
});

test('timing changes only the selected owned session and never status policy or delivery', function (): void {
    $deliveries = NotificationDelivery::query()->count();
    $policy = $this->session->notification_policy_snapshot;
    $result = warpOwnedTimeline($this->session, '1h-reminder', $this->operator, $this->set);

    expect($result['session']->starts_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue()
        ->and($result['session']->effective_end_at->diffInMinutes($result['session']->starts_at, true))->toEqual(120)
        ->and($result['session']->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($result['session']->notification_policy_snapshot)->toBe($policy)
        ->and(NotificationDelivery::query()->count())->toBe($deliveries);

    warpOwnedTimeline($this->session, 'live', $this->operator, $this->set);
    expect($this->session->fresh()->starts_at->isPast())->toBeTrue()
        ->and($this->session->fresh()->effective_end_at->isFuture())->toBeTrue()
        ->and($this->session->fresh()->status)->toBe(ExamSession::STATUS_DRAFT);
});

test('unknown code-selected institutional session cannot be warped or adopted', function (): void {
    $unknown = ExamSession::factory()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id, 'room_id' => $this->session->room_id,
        'code' => 'EX-STAGE-RESV', 'starts_at' => now()->addDays(12), 'effective_end_at' => now()->addDays(12)->addHours(2),
    ]);
    $before = $unknown->fresh()->getAttributes();

    expect(fn () => warpOwnedTimeline($unknown, '24h-reminder', $this->operator, $this->set))
        ->toThrow(RuntimeException::class);
    expect($unknown->fresh()->getAttributes())->toBe($before);
});

test('timing rejects foreign set owner retired set and completed history', function (): void {
    $foreignOperator = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    expect(fn () => warpOwnedTimeline($this->session, 'live', $foreignOperator, $this->set))
        ->toThrow(RuntimeException::class);
    $completed = app(PracticeWorkspace::class)->run($this->set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $this->set->academic_cycle_id, 'room_id' => $this->session->room_id,
        'status' => ExamSession::STATUS_COMPLETED, 'starts_at' => now()->subDays(2), 'effective_end_at' => now()->subDays(2)->addHours(2),
    ]));
    expect(fn () => warpOwnedTimeline($completed, 'ended', $this->operator, $this->set))->toThrow(RuntimeException::class);
    $this->set->update(['status' => 'retired', 'retired_at' => now()]);
    expect(fn () => warpOwnedTimeline($this->session, 'live', $this->operator, $this->set))
        ->toThrow(RuntimeException::class);
});

test('timing checks fresh authority rather than previously loaded grants', function (): void {
    $this->operator->load('roles.permissions', 'permissions');
    $this->operator->syncRoles([]);
    $this->operator->revokePermissionTo(CapabilityCatalog::DEMO_STAGE_CONTROL);
    $before = $this->session->fresh()->starts_at->toISOString();

    expect(fn () => warpOwnedTimeline($this->session, '1h-reminder', $this->operator, $this->set))
        ->toThrow(RuntimeException::class);
    expect($this->session->fresh()->starts_at->toISOString())->toBe($before);
});

test('timeline invalid presets and CLI without exact selectors fail without changing rows', function (): void {
    $before = $this->session->fresh()->getAttributes();
    expect(fn () => warpOwnedTimeline($this->session, 'invalid-stage', $this->operator, $this->set))
        ->toThrow(InvalidArgumentException::class);
    expect(Artisan::call('demo:touch'))->toBe(0)
        ->and(Artisan::call('demo:warp'))->toBe(0)
        ->and(Artisan::call('demo:warp', ['session' => (string) $this->session->id, '--stage' => 'live']))->toBe(1)
        ->and($this->session->fresh()->getAttributes())->toBe($before);
});

test('timing uses the sealed preview reference instead of recomputing a new target on confirmation', function (): void {
    $reference = CarbonImmutable::now()->subMinutes(5);
    $target = $reference->addHour();

    $result = warpOwnedTimeline($this->session, '1h-reminder', $this->operator, $this->set, $reference);

    expect($result['session']->starts_at->toDateTimeString())->toBe($target->toDateTimeString())
        ->and($result['session']->effective_end_at->toDateTimeString())->toBe($target->addHours(2)->toDateTimeString());
});
