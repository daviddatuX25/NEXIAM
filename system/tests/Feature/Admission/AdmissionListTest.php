<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admissions index denies users without registrar view capability', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('admissions.index'))
        ->assertForbidden();
});

test('admissions index returns filterable paginated applications payload', function (): void {
    seedE2Foundation();
    $fixturePending = AdmissionAttempt::query()->where('status', 'pending')->count();
    $pending = submitSyntheticApplicant('pending.list@example.test', 'Pending');
    $accepted = submitSyntheticApplicant('accepted.list@example.test', 'Accepted');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $accepted['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Index')
            ->where('page_state', 'ready')
            ->where('filters.status', 'pending')
            ->has('attempts.data', $fixturePending + 1)
            ->where('status_counts.pending', $fixturePending + 1)
            ->where('status_counts.accepted', 1)
            ->where('capabilities.accept', true)
            ->has('cycles')
            ->has('statuses')
            ->where('attempts.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['reference_code'] === $pending['attempt']->reference_code
            ))
        );
});

test('admissions index search matches applicant email and reference', function (): void {
    seedE2Foundation();
    $match = submitSyntheticApplicant('search.match@example.test', 'Matcher');
    submitSyntheticApplicant('other.person@example.test', 'Other');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['search' => 'search.match']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'search.match')
            ->has('attempts.data', 1)
            ->where('attempts.data.0.contact_email', 'search.match@example.test')
        );

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['search' => $match['attempt']->reference_code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attempts.data', 1)
            ->where('attempts.data.0.reference_code', $match['attempt']->reference_code)
        );
});

test('admissions index filters by academic cycle code', function (): void {
    seedE2Foundation();
    submitSyntheticApplicant('cycle.filter@example.test');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $totalInCycle = AdmissionAttempt::query()->count();

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['cycle' => 'SYN-CYCLE-2026']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.cycle', 'SYN-CYCLE-2026')
            ->has('attempts.data', $totalInCycle)
            ->where('attempts.data.0.cycle_code', 'SYN-CYCLE-2026')
        );

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['cycle' => 'NO-SUCH-CYCLE']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.cycle', 'NO-SUCH-CYCLE')
            ->has('attempts.data', 0)
        );
});

test('admissions index paginates beyond fifteen results', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $seeded = AdmissionAttempt::query()->count();

    for ($i = 0; $i < 16; $i++) {
        submitSyntheticApplicant("page{$i}@example.test", "Page{$i}");
    }

    $total = $seeded + 16;
    expect(AdmissionAttempt::query()->count())->toBe($total)
        ->and($total)->toBeGreaterThan(15);

    $this->actingAs($registrar)
        ->get(route('admissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attempts.data', 15)
            ->where('attempts.current_page', 1)
            ->where('attempts.last_page', (int) ceil($total / 15))
            ->where('attempts.total', $total)
        );

    $this->actingAs($registrar)
        ->get(route('admissions.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attempts.data', $total - 15)
            ->where('attempts.current_page', 2)
        );
});
