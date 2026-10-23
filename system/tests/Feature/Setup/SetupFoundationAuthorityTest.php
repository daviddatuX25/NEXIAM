<?php

use App\Foundation\Actions\UpdateRoom;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use App\Setup\Actions\ActivateAcademicCycle;
use App\Setup\Actions\DeactivateAcademicCycle;
use App\Setup\Actions\StoreApplicationWindow;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
});

test('ActivateAcademicCycle enforces singleton active cycle and emits audit', function (): void {
    $secondCycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-2ND',
        'name' => '2026-2027 1st Semester',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-08-01',
        'closes_on' => '2027-01-31',
        'is_active' => false,
    ]);

    $window = ApplicationWindow::query()->create([
        'academic_cycle_id' => $secondCycle->id,
        'code' => 'WIN-2ND-A',
        'name' => 'Second Window',
        'opens_at' => '2026-08-01 00:00:00',
        'closes_at' => '2026-10-31 23:59:59',
        'status' => 'draft',
    ]);

    app(ActivateAcademicCycle::class)->handle($secondCycle, $this->registrar);

    expect($secondCycle->fresh()->is_active)->toBeTrue()
        ->and($this->cycle->fresh()->is_active)->toBeFalse()
        ->and($window->fresh()->status)->toBe('published')
        ->and(AcademicCycle::query()->where('institution_id', $this->institution->id)->where('is_active', true)->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'foundation.cycle_activated')->where('payload->cycle_id', $secondCycle->id)->count())->toBe(1);

    app(DeactivateAcademicCycle::class)->handle($secondCycle, $this->registrar);

    expect($secondCycle->fresh()->is_active)->toBeFalse()
        ->and($window->fresh()->status)->toBe('closed')
        ->and(AcademicCycle::query()->where('institution_id', $this->institution->id)->where('is_active', true)->count())->toBe(0);
});

test('StoreApplicationWindow enforces institution scope and date bounds within cycle', function (): void {
    $otherInstitution = Institution::query()->create(['code' => 'OTHER-INST-99', 'name' => 'Other Inst']);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'CYCLE-FOREIGN',
        'name' => 'Foreign Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);

    // Foreign cycle rejected
    expect(fn () => app(StoreApplicationWindow::class)->handle([
        'academic_cycle_id' => $foreignCycle->id,
        'code' => 'WIN-FOREIGN',
        'name' => 'Foreign Window',
        'opens_at' => '2026-02-01 00:00:00',
        'closes_at' => '2026-04-30 23:59:59',
        'status' => 'published',
    ], $this->registrar))->toThrow(ValidationException::class);

    // Closing time before opening time rejected
    expect(fn () => app(StoreApplicationWindow::class)->handle([
        'academic_cycle_id' => $this->cycle->id,
        'code' => 'WIN-REVERSED',
        'name' => 'Reversed Window',
        'opens_at' => '2026-05-01 00:00:00',
        'closes_at' => '2026-04-01 00:00:00',
        'status' => 'published',
    ], $this->registrar))->toThrow(ValidationException::class);

    // Dates outside cycle bounds rejected
    expect(fn () => app(StoreApplicationWindow::class)->handle([
        'academic_cycle_id' => $this->cycle->id,
        'code' => 'WIN-OUTSIDE',
        'name' => 'Outside Window',
        'opens_at' => '2025-12-01 00:00:00',
        'closes_at' => '2026-04-01 00:00:00',
        'status' => 'published',
    ], $this->registrar))->toThrow(ValidationException::class);

    // Valid window stored and audited
    $stored = app(StoreApplicationWindow::class)->handle([
        'academic_cycle_id' => $this->cycle->id,
        'code' => 'WIN-VALID-SETUP',
        'name' => 'Valid Setup Window',
        'opens_at' => '2026-03-01 00:00:00',
        'closes_at' => '2026-05-01 23:59:59',
        'status' => 'published',
    ], $this->registrar);

    expect($stored->code)->toBe('WIN-VALID-SETUP')
        ->and(AuditEvent::query()->where('event_type', 'setup.window_upserted')->where('payload->code', 'WIN-VALID-SETUP')->count())->toBe(1);
});

test('legacy privacy publishing route is retired while historical privacy rows remain readable', function (): void {
    $policy = PrivacyPolicy::query()->create([
        'title' => 'Historical institutional privacy notice',
        'content' => 'Retained for historical provenance only.',
        'is_active' => true,
    ]);

    expect($policy->fresh()->title)->toBe('Historical institutional privacy notice')
        ->and(Route::has('setup.privacy'))->toBeFalse()
        ->and(Route::has('setup.privacy.store'))->toBeFalse()
        ->and(Route::has('setup.privacy.activate'))->toBeFalse();

    $this->actingAs($this->registrar)->get('/setup/privacy')->assertNotFound();
    $this->actingAs($this->registrar)->post('/setup/privacy', [
        'title' => 'Unapproved replacement',
        'content' => 'No legacy writer remains.',
        'is_active' => true,
    ])->assertNotFound();
    $this->actingAs($this->registrar)->post('/setup/privacy/1/activate')->assertNotFound();
});

test('UpdateRoom guards against deactivating or changing space_kind when room has active future sessions', function (): void {
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-FUTURE-ROOM-TEST',
        'starts_at' => now()->addDays(5),
        'effective_end_at' => now()->addDays(5)->addHours(2),
        'status' => ExamSession::STATUS_DRAFT,
        'version' => 1,
    ]);

    // Deactivation rejected
    expect(fn () => app(UpdateRoom::class)->handle($room, [
        'name' => $room->name,
        'capacity' => $room->capacity,
        'is_active' => false,
    ], $this->counselor))->toThrow(ValidationException::class);

    // Changing space_kind to non-room rejected
    expect(fn () => app(UpdateRoom::class)->handle($room, [
        'name' => $room->name,
        'capacity' => $room->capacity,
        'is_active' => true,
        'space_kind' => SpaceKind::AssemblyVenue,
    ], $this->counselor))->toThrow(ValidationException::class);

    // After session cancelled, deactivation allowed
    $examSession->update(['status' => ExamSession::STATUS_CANCELLED]);

    $deactivated = app(UpdateRoom::class)->handle($room, [
        'name' => $room->name,
        'capacity' => $room->capacity,
        'is_active' => false,
    ], $this->counselor);

    expect($deactivated->is_active)->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'setup.room_updated')->where('payload->room_id', $room->id)->count())->toBeGreaterThan(0);
});
