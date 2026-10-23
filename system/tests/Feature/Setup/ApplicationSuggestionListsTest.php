<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Foundation\Actions\SaveApplicationSuggestionList;
use App\Models\Foundation\ApplicationSuggestionList;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\ApplicationSuggestionListSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function applicationSuggestionStaff(): User
{
    seedE2Foundation();
    $user = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    ApplicationSuggestionList::query()->delete();

    return $user;
}

function previousSchoolSuggestionEntries(): array
{
    $reference = json_decode(
        file_get_contents(database_path('data/application-suggestions.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_map(
        static fn (array $record): array => ['label' => $record['label'], 'aliases' => $record['aliases']],
        $reference['lists']['schools'],
    );
}

it('saves optional name-only lists and projects the saved records on public Apply without GET writes', function (): void {
    $staff = applicationSuggestionStaff();
    $institution = Institution::current();

    $this->actingAs($staff)->get(route('setup.application-suggestions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/ApplicationSuggestions')
            ->where('suggestion_lists.schools.revision', 0)
            ->where('suggestion_lists.schools.entries', [])
        );
    expect(ApplicationSuggestionList::query()->count())->toBe(0);

    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools',
        'expected_revision' => 0,
        'entries' => [
            ['label' => ' Example   School ', 'aliases' => ['ES', ' ES ', 'Example School']],
            ['label' => 'Name Only School'],
            ['label' => ' example school ', 'aliases' => ['Additional Alias']],
        ],
    ])->assertRedirect();

    expect(ApplicationSuggestionList::query()->where('institution_id', $institution->id)->firstOrFail()->entries)
        ->toBe([
            ['label' => 'Example School', 'aliases' => ['ES', 'Additional Alias']],
            ['label' => 'Name Only School', 'aliases' => []],
        ]);
    $this->get(route('apply.form'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('intake_suggestions.schools.0.label', 'Example School')
        ->where('intake_suggestions.schools.0.aliases', ['ES', 'Additional Alias'])
        ->has('intake_suggestions.shs', 0)
        ->has('intake_suggestions.programs', 0)
        ->missing('intake_suggestions.schools.0.revision')
    );
});

it('accepts an empty list save and rejects stale edits while allowing exact replay without duplicate audit', function (): void {
    $staff = applicationSuggestionStaff();
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0,
        'entries' => [['label' => 'Example School', 'aliases' => []]],
    ])->assertRedirect();

    $first = ApplicationSuggestionList::query()->where('category', 'schools')->firstOrFail();
    $auditCount = DB::table('audit_events')->where('event_type', 'setup.application_suggestions_saved')->count();
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0,
        'entries' => [['label' => 'Example School']],
    ])->assertRedirect();
    expect(DB::table('audit_events')->where('event_type', 'setup.application_suggestions_saved')->count())->toBe($auditCount)
        ->and(ApplicationSuggestionList::query()->where('category', 'schools')->firstOrFail()->revision)->toBe($first->revision);

    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0,
        'entries' => [['label' => 'Different School']],
    ])->assertSessionHasErrors('expected_revision');
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 1, 'entries' => [],
    ])->assertRedirect();
    expect(ApplicationSuggestionList::query()->where('category', 'schools')->firstOrFail()->entries)->toBe([]);
});

it('denies guests and actors scoped to another institution', function (): void {
    applicationSuggestionStaff();
    $this->get(route('setup.application-suggestions'))->assertRedirect(route('login'));

    $foreign = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $foreignInstitution = Institution::query()->create(['code' => 'FOREIGN-SUGGESTIONS', 'name' => 'Other institution']);
    $foreign->staffProfile()->update(['institution_id' => $foreignInstitution->getKey()]);
    $this->actingAs($foreign)->get(route('setup.application-suggestions'))->assertForbidden();
    $this->actingAs($foreign)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0, 'entries' => [],
    ])->assertForbidden();
    expect(ApplicationSuggestionList::query()->count())->toBe(0);
    $unprivileged = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($unprivileged)->get(route('setup.application-suggestions'))->assertForbidden();
    $this->actingAs($unprivileged)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0, 'entries' => [],
    ])->assertForbidden();
});

it('rolls back list persistence when audit recording fails', function (): void {
    $staff = applicationSuggestionStaff();
    $this->mock(RecordAuditEvent::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));

    expect(fn () => app(SaveApplicationSuggestionList::class)->handle([
        'category' => 'schools', 'expected_revision' => 0,
        'entries' => [['label' => 'Example School']],
    ], $staff))->toThrow(RuntimeException::class, 'audit unavailable');
    expect(ApplicationSuggestionList::query()->count())->toBe(0);
});

it('seeds missing suggestions once but preserves an intentionally empty staff list', function (): void {
    seedE2Foundation();
    ApplicationSuggestionList::query()->delete();
    (new ApplicationSuggestionListSeeder)->run();
    $seededCount = ApplicationSuggestionList::query()->count();
    expect($seededCount)->toBe(3);

    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 1, 'entries' => [],
    ])->assertRedirect();
    (new ApplicationSuggestionListSeeder)->run();
    expect(ApplicationSuggestionList::query()->count())->toBe($seededCount)
        ->and(ApplicationSuggestionList::query()->where('category', 'schools')->firstOrFail()->entries)->toBe([]);
});

it('rejects aliases exceeding the per-name limit after duplicate rows are merged', function (): void {
    $staff = applicationSuggestionStaff();
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools',
        'expected_revision' => 0,
        'entries' => [
            ['label' => 'Example School', 'aliases' => array_map(fn (int $i): string => "Alias {$i}", range(1, 11))],
            ['label' => 'example school', 'aliases' => array_map(fn (int $i): string => "Alias {$i}", range(12, 22))],
        ],
    ])->assertSessionHasErrors('entries');
    expect(ApplicationSuggestionList::query()->count())->toBe(0);
});

it('shows suggestion saves in Registrar audit filters without exposing unrelated setup events', function (): void {
    $staff = applicationSuggestionStaff();
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools', 'expected_revision' => 0,
        'entries' => [['label' => 'Audit Example School']],
    ])->assertRedirect();
    app(RecordAuditEvent::class)->handle('setup.unrelated_private_change', [
        'institution_id' => Institution::current()->getKey(),
    ], (string) $staff->getKey());
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($admin)->get('/staff/logs?category=setup')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audit.logs', fn ($logs): bool => collect($logs)->pluck('event_type')->contains('setup.application_suggestions_saved')
                && ! collect($logs)->pluck('event_type')->contains('setup.unrelated_private_change'))
            ->where('audit.events', fn ($events): bool => collect($events)->contains('setup.application_suggestions_saved')
                && ! collect($events)->contains('setup.unrelated_private_change'))
        );
});

it('refreshes registered untouched defaults once and rejects a stale editor afterward', function (): void {
    $staff = applicationSuggestionStaff();
    $list = ApplicationSuggestionList::query()->create([
        'institution_id' => Institution::current()->getKey(),
        'category' => 'schools',
        'entries' => previousSchoolSuggestionEntries(),
        'revision' => 1,
    ]);
    $this->actingAs($staff)->get(route('setup.application-suggestions'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestion_lists.schools.revision', 1));

    (new ApplicationSuggestionListSeeder)->run();
    $refreshedEntries = $list->fresh()->entries;
    expect($list->fresh()->revision)->toBe(2);
    $audit = DB::table('audit_events')->where('event_type', 'setup.application_suggestions_saved')
        ->where('payload->source', 'reviewed_seed');
    expect($audit->count())->toBe(1);
    $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
        'category' => 'schools',
        'expected_revision' => 1,
        'entries' => [['label' => 'Old editor school']],
    ])->assertSessionHasErrors('expected_revision');
    expect($list->fresh()->entries)->toBe($refreshedEntries);
    (new ApplicationSuggestionListSeeder)->run();
    expect($list->fresh()->revision)->toBe(2)
        ->and($audit->count())->toBe(1);
});

it('does not replace staff-owned, later-revision, empty or unknown seed lists', function (int $revision, bool $staffOwned, string $content): void {
    $staff = applicationSuggestionStaff();
    $entries = match ($content) {
        'baseline' => previousSchoolSuggestionEntries(),
        'empty' => [],
        default => [['label' => 'My unregistered school', 'aliases' => ['MUS']]],
    };
    $list = ApplicationSuggestionList::query()->create([
        'institution_id' => Institution::current()->getKey(),
        'category' => 'schools',
        'entries' => $entries,
        'revision' => $revision,
        'updated_by_user_id' => $staffOwned ? $staff->getKey() : null,
    ]);
    (new ApplicationSuggestionListSeeder)->run();
    expect($list->fresh()->entries)->toBe($entries)
        ->and($list->fresh()->revision)->toBe($revision)
        ->and($list->fresh()->updated_by_user_id)->toBe($staffOwned ? $staff->getKey() : null);
})->with([
    'staff owns even an old matching baseline' => [1, true, 'baseline'],
    'later revision survives without an updater' => [2, false, 'baseline'],
    'intentionally empty list' => [1, false, 'empty'],
    'unregistered existing contents' => [1, false, 'custom'],
]);

it('rolls back a seed refresh when its audit cannot be recorded', function (): void {
    applicationSuggestionStaff();
    $entries = previousSchoolSuggestionEntries();
    $list = ApplicationSuggestionList::query()->create([
        'institution_id' => Institution::current()->getKey(),
        'category' => 'schools',
        'entries' => $entries,
        'revision' => 1,
    ]);
    $this->mock(RecordAuditEvent::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('seed audit unavailable'));
    expect(fn () => (new ApplicationSuggestionListSeeder)->run())
        ->toThrow(RuntimeException::class, 'seed audit unavailable');
    expect($list->fresh()->entries)->toBe($entries)
        ->and($list->fresh()->revision)->toBe(1);
});

it('requires matching seed audit before refreshing a registered later seed revision', function (bool $hasSeedEvidence): void {
    $staff = applicationSuggestionStaff();
    (new ApplicationSuggestionListSeeder)->run();
    $list = ApplicationSuggestionList::query()->where('category', 'schools')->firstOrFail();
    $source = json_decode(
        file_get_contents(database_path('data/region-i-public-school-suggestions.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $restoredName = collect($source['records'])->firstWhere('key', 'deped-r1-100001')['label'];
    // Reconstruct the published interim snapshot that omitted the workbook's first school.
    $earlierEntries = array_values(array_filter(
        $list->entries,
        static fn (array $entry): bool => $entry['label'] !== $restoredName,
    ));
    $list->update(['entries' => $earlierEntries, 'revision' => 2]);
    if ($hasSeedEvidence) {
        app(RecordAuditEvent::class)->handle('setup.application_suggestions_saved', [
            'institution_id' => Institution::current()->getKey(),
            'category' => 'schools',
            'revision' => 2,
            'hash' => hash('sha256', json_encode($earlierEntries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'source' => 'reviewed_seed',
        ]);
    }

    (new ApplicationSuggestionListSeeder)->run();
    expect($list->fresh()->revision)->toBe($hasSeedEvidence ? 3 : 2);
    if ($hasSeedEvidence) {
        $this->actingAs($staff)->put(route('setup.application-suggestions.update'), [
            'category' => 'schools',
            'expected_revision' => 2,
            'entries' => [['label' => 'Stale source-correction draft']],
        ])->assertSessionHasErrors('expected_revision');
    } else {
        expect($list->fresh()->entries)->toBe($earlierEntries);
    }
})->with(['audited source-owned snapshot' => true, 'same fingerprint without seed evidence' => false]);
