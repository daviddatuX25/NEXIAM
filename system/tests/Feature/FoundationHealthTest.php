<?php

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
});

afterEach(function (): void {
    $this->travelBack();
});

test('home page renders the institution-first public landing contract', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('institution.name', 'Ilocos Sur Polytechnic State College')
            ->where('institution.campus', 'Tagudin Campus')
            ->where('activeSeason', null)
            ->has('hero.image.src')
            ->where('programs.0.isSimulated', true)
            ->has('programs')
            ->where('preview.source', 'simulated')
        )
        ->assertDontSee('E2-CONNECTED-SLICE')
        ->assertDontSee('Foundation runtime is alive')
        ->assertDontSee('E1b flips');
});

test('home landing exposes the active academic year and effective intake status', function (): void {
    seedE2Foundation();

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('activeSeason', [
                'academic_year' => '2025-2026',
                'is_open' => true,
            ])
            ->where('programs.0.isSimulated', false)
        );
});

test('home landing reports closed windows and out-of-date cycles as unavailable', function (): void {
    seedE2Foundation();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    $window->update(['status' => 'closed']);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activeSeason', [
            'academic_year' => '2025-2026',
            'is_open' => false,
        ]));

    $window->update([
        'status' => 'published',
        'closes_at' => '2026-09-24 23:59:59',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activeSeason', [
            'academic_year' => '2025-2026',
            'is_open' => false,
        ]));

    $window->update(['closes_at' => '2026-09-30 23:59:59']);
    AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->update(['closes_on' => '2026-09-24']);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activeSeason', [
            'academic_year' => '2025-2026',
            'is_open' => false,
        ]));
});

test('home landing ignores foreign active cycles and windows', function (): void {
    seedE2Foundation();
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-INST',
        'name' => 'Foreign Institution',
    ]);
    AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'FOREIGN-CYCLE',
        'name' => '2099-2100',
        'school_year' => '2099-2100',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);
    ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')
        ->update(['academic_cycle_id' => AcademicCycle::query()->where('code', 'FOREIGN-CYCLE')->value('id')]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activeSeason', [
            'academic_year' => '2025-2026',
            'is_open' => false,
        ]));
});

test('home landing returns no active season when no cycle is active', function (): void {
    seedE2Foundation();
    AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->update(['is_active' => false]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('activeSeason', null));
});

test('health endpoint reports database readiness', function (): void {
    // phpunit.xml forces QUEUE_CONNECTION=sync for isolation; assert the health
    // payload truthfully reflects the production queue shape under test.
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.after_commit', true);

    $response = $this->getJson('/health');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('database', 'ok')
        ->assertJsonPath('private_storage', 'ok')
        ->assertJsonPath('queue', 'configured_after_commit');
});

test('apply page exposes programs and open window without slice meta labels', function (): void {
    seedE2Foundation();

    $this->get('/apply')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->where('page_state', 'ready')
            ->has('window.id')
            ->has('cycle.id')
        )
        ->assertDontSee('E2 connected slice')
        ->assertDontSee('Public intake (E2');
});
