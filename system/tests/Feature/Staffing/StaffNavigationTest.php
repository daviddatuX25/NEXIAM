<?php

use App\Models\User;
use App\Support\Navigation\StaffNavigationBuilder;

function flattenNavSections(array $sections): array
{
    return collect($sections)
        ->flatMap(fn (array $section) => $section['items'])
        ->values()
        ->all();
}

test('staff navigation marks home exact so personnel is not double-active', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $sections = app(StaffNavigationBuilder::class)->for($hr);
    $home = collect($sections)->firstWhere('label', null)['items'][0] ?? null;
    $personnel = collect(flattenNavSections($sections))->firstWhere('href', '/staff/personnel');

    expect($home)->not->toBeNull()
        ->and($home['match'])->toBe('exact')
        ->and($personnel)->not->toBeNull()
        ->and($personnel['match'])->toBe('prefix');
});

test('staff navigation includes staff page for all staff', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $hrHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($hr)))->pluck('href');
    $clerkHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($clerk)))->pluck('href');
    $guidanceHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($guidance)))->pluck('href');

    expect($hrHrefs)->toContain('/staff/personnel')
        ->and($clerkHrefs)->toContain('/staff/personnel')
        ->and($guidanceHrefs)->toContain('/staff/personnel')
        ->and($clerkHrefs)->toContain('/admissions')
        ->and($hrHrefs)->not->toContain('/staff/scan')
        ->and($clerkHrefs)->not->toContain('/staff/scan')
        ->and($guidanceHrefs)->not->toContain('/staff/scan');
});

test('exam navigation separates scheduling from monitoring and drops standalone proctor', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $sections = app(StaffNavigationBuilder::class)->for($guidance);
    $admissions = collect($sections)->firstWhere('label', 'Admissions');
    $assessment = collect($sections)->firstWhere('label', 'Assessment');
    $admissionsHrefs = collect($admissions['items'])->pluck('href');
    $assessmentHrefs = collect($assessment['items'])->pluck('href');
    $allHrefs = collect(flattenNavSections($sections))->pluck('href');
    $allLabels = collect(flattenNavSections($sections))->pluck('label');

    expect($admissionsHrefs)->toContain('/exams/sessions')
        ->and($assessmentHrefs)->toContain('/exams/monitoring')
        ->and($allHrefs)->not->toContain('/proctor')
        ->and($allLabels)->toContain('Scoring')
        ->and($allHrefs)->not->toContain('/ui-kit');
});

test('exam monitoring nav is shown to proctor-duty holders without schedule capability', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    // Registrar clerk holds no exam-schedule capability; only an active duty
    // overlay grants monitoring access.
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $cycle = App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = App\Models\Foundation\Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NAV-DUTY-1',
        'date' => now()->addDays(4)->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $session = App\Models\Examination\ExamSession::query()->where('code', 'EX-NAV-DUTY-1')->firstOrFail();

    App\Models\Examination\ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $registrar->id,
        'status' => App\Models\Examination\ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'nav-duty-1',
        'assigned_at' => now(),
    ]);

    $sections = app(StaffNavigationBuilder::class)->for($registrar);
    $assessment = collect($sections)->firstWhere('label', 'Assessment');
    $hrefs = collect($assessment['items'] ?? [])->pluck('href');

    expect($hrefs)->toContain('/exams/monitoring')
        ->and($hrefs)->not->toContain('/exams/sessions');
});
