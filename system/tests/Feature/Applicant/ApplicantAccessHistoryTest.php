<?php

use App\Foundation\Actions\RecordAuditEvent;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

function createAccessHistoryAttempt(TestCase $test, string $email): array
{
    return buildMessagingReadyAttempt($test, $email);
}

test('applicant audit history is limited to own attempts and actions', function (): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-owner@example.test');
    [$otherAttempt, $otherApplicant] = createAccessHistoryAttempt($this, 'access-history-other@example.test');
    $record = app(RecordAuditEvent::class);

    $record->handle('exam.slot_reserved', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    $record->handle('admission.applicant_login', ['user_id' => $applicant->id], (string) $applicant->id);
    $record->handle('admission.applicant_voucher_login', ['user_id' => $applicant->id], (string) $applicant->email);
    $record->handle('exam.slot_reserved', ['attempt_id' => (string) $otherAttempt->id], (string) $guidance->id);
    $record->handle('admission.applicant_login', ['user_id' => $otherApplicant->id], (string) $otherApplicant->id);

    $this->actingAs($applicant)->get(route('applicant.access-history'))
        ->assertOk()
        ->assertInertia(function ($page): void {
            $page->component('Applicant/AccessHistory')
                ->where('events.total', 4)
                ->has('events.data', 4);
            $directions = array_column($page->toArray()['props']['events']['data'], 'direction');
            expect($directions)->toContain('on_your_record', 'by_you');
        });
});

test('actor matching cannot include events for another applicant target', function (): void {
    [$attempt, $applicant] = createAccessHistoryAttempt($this, 'access-history-actor-owner@example.test');
    [$otherAttempt] = createAccessHistoryAttempt($this, 'access-history-actor-other@example.test');

    $record = app(RecordAuditEvent::class);
    $foreignEvent = $record->handle(
        'exam.slot_reserved',
        ['attempt_id' => (string) $otherAttempt->id],
        (string) $applicant->id,
    );
    $ownEvent = $record->handle(
        'exam.slot_reserved',
        ['attempt_id' => (string) $attempt->id],
        (string) $applicant->id,
    );
    $unapprovedEvent = $record->handle(
        'admission.access_challenge_issued',
        ['attempt_id' => (string) $attempt->id],
        (string) $applicant->id,
    );

    $response = $this->actingAs($applicant)->get(route('applicant.access-history', ['search' => 'you']));
    $response->assertOk()->assertInertia(function ($page) use ($foreignEvent, $ownEvent, $unapprovedEvent): void {
        $rows = $page->toArray()['props']['events']['data'];
        $ids = array_column($rows, 'id');
        expect($ids)->toContain((string) $ownEvent->getKey())
            ->not->toContain((string) $foreignEvent->getKey())
            ->not->toContain((string) $unapprovedEvent->getKey());
        expect(collect($rows)->firstWhere('id', (string) $ownEvent->getKey()))
            ->toMatchArray(['event_type' => 'exam.slot_reserved', 'direction' => 'by_you']);
    });

});

test('applicant history uses producer event keys and paginates beyond twenty five', function (): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-producer@example.test');
    $record = app(RecordAuditEvent::class);

    for ($index = 0; $index < 25; $index++) {
        $record->handle('exam.slot_reserved', [
            'attempt_id' => (string) $attempt->id,
            'assignment_id' => 'assignment-'.$index,
        ], (string) $guidance->id);
    }

    $record->handle('exam.assignment_created', [
        'attempt_id' => (string) $attempt->id,
        'exam_session_id' => 'session-1',
    ], (string) $guidance->id);

    $this->actingAs($applicant)->get(route('applicant.access-history', [
        'category' => 'examination',
        'sort' => 'oldest',
        'page' => 2,
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('events.total', 26)
            ->where('events.per_page', 25)
            ->where('events.current_page', 2)
            ->has('events.data', 1)
        );

    $this->get(route('applicant.access-history', [
        'category' => 'examination',
        'search' => 'session assigned',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('events.total', 1)
            ->where('events.data.0.event_type', 'exam.assignment_created')
            ->where('events.data.0.label', 'Examination session assigned')
        );
});

test('applicant audit history filters by category and actor office', function (): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-filter@example.test');
    $record = app(RecordAuditEvent::class);
    $record->handle('exam.slot_reserved', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    $record->handle('program.head_record_viewed', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);

    $this->actingAs($applicant)->get(route('applicant.access-history', [
        'category' => 'examination',
        'actor_office' => 'guidance',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', 'examination')
            ->where('filters.actor_office', 'guidance')
            ->where('events.total', 1)
            ->where('events.data.0.event_type', 'exam.slot_reserved')
            ->where('events.data.0.actor_display', 'Guidance Office')
        );
});

test('applicant audit category filters accept a blank search', function (string $category, string $search): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-blank-search@example.test');
    $record = app(RecordAuditEvent::class);
    $record->handle('exam.slot_reserved', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);

    $this->actingAs($applicant)->get(route('applicant.access-history', [
        'category' => $category,
        'actor_office' => 'all',
        'search' => $search,
        'sort' => 'newest',
        'page' => 1,
    ]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', $category)
            ->where('filters.search', '')
            ->where('events.total', in_array($category, ['all', 'examination', 'security'], true) ? ($category === 'all' ? 2 : 1) : 0)
        );
})->with(['all', 'application', 'examination', 'results_release', 'clearance', 'security'])
    ->with(['', '   ']);

test('applicant audit search still rejects arrays and overlong text', function (mixed $search): void {
    [, $applicant] = createAccessHistoryAttempt($this, 'access-history-invalid-search@example.test');

    $this->actingAs($applicant)->getJson(route('applicant.access-history', ['search' => $search]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search');
})->with([
    'array' => [['unexpected']],
    'overlong text' => [str_repeat('a', 121)],
]);

test('applicant audit history filters by all individual categories', function (): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-categories@example.test');
    $record = app(RecordAuditEvent::class);

    // Application
    $record->handle('admission.attempt_accepted', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    // Examination
    $record->handle('exam.slot_reserved', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    // Results & Release
    $record->handle('release.result_released', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    // Clearance
    $record->handle('registrar.eligibility_lookup', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    $record->handle('admission.clearance.confirmed', ['attempt_id' => (string) $attempt->id], (string) $guidance->id);
    // Security
    $record->handle('admission.applicant_login', ['user_id' => $applicant->id], (string) $applicant->id);

    // Verify Clearance category
    $this->actingAs($applicant)->get(route('applicant.access-history', ['category' => 'clearance']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', 'clearance')
            ->where('events.total', 2)
            ->where('category_counts.clearance', 2)
        );

    // Verify Application category
    $this->actingAs($applicant)->get(route('applicant.access-history', ['category' => 'application']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', 'application')
            ->where('events.total', 1)
        );

    // Verify Results & Release category
    $this->actingAs($applicant)->get(route('applicant.access-history', ['category' => 'results_release']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', 'results_release')
            ->where('events.total', 1)
        );

    // Verify Security category
    $this->actingAs($applicant)->get(route('applicant.access-history', ['category' => 'security']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AccessHistory')
            ->where('filters.category', 'security')
            ->where('events.total', 2)
        );
});

test('applicant audit props contain only safe event fields', function (): void {
    [$attempt, $applicant, $guidance] = createAccessHistoryAttempt($this, 'access-history-private@example.test');
    app(RecordAuditEvent::class)->handle('program.head_record_viewed', [
        'attempt_id' => (string) $attempt->id,
        'internal_note' => 'confidential staff note sentinel',
        'staff_email' => 'staff-private-sentinel@example.test',
        'ip_address' => '192.0.2.77',
    ], (string) $guidance->id);

    $response = $this->actingAs($applicant)->get(route('applicant.access-history'));
    $response->assertOk();
    expect($response->getContent())
        ->not->toContain('confidential staff note sentinel')
        ->not->toContain('staff-private-sentinel@example.test')
        ->not->toContain('192.0.2.77');

    $response->assertInertia(function ($page): void {
        $rows = $page->toArray()['props']['events']['data'];
        expect($rows)->toHaveCount(2)
            ->and(array_keys($rows[0]))->toBe([
                'id', 'event_type', 'label', 'category', 'actor_display', 'direction', 'occurred_at',
            ]);
    });
});
