<?php

use App\Assessment\Omr\Queries\BookletWorkspaceQuery;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\UpdateDraftExamSession;
use App\Examination\Support\ExamSessionPresenter;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $this->staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->staff->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
    ]);
    $state = StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $this->staff->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'mfa_required' => false,
            'must_change_password' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_generation' => (string) Str::uuid(),
        ],
    );
    $this->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => 'booklet-lifecycle-test',
    ]);
});

function bookletLifecycleVersion(
    OmrQuestionBooklet $booklet,
    int $number,
    string $status,
    ?string $finalizedAt = null,
    ?string $archivedAt = null,
): OmrQuestionBookletVersion {
    $answerKey = ['LOGIC_Q_001' => 'A'];
    $version = $booklet->versions()->create([
        'version' => $number,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', json_encode($answerKey, JSON_THROW_ON_ERROR)),
        'content_hash' => hash('sha256', json_encode(['answer_key' => $answerKey], JSON_THROW_ON_ERROR)),
    ]);

    if ($status === OmrQuestionBookletVersion::STATUS_FINALIZED) {
        $version->update([
            'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
            'finalized_by_user_id' => (int) User::query()->where('email', 'guidance.counselor@nexiam.test')->value('id'),
            'finalized_at' => $finalizedAt ?? now(),
        ]);
    }
    if ($archivedAt !== null) {
        $version->update(['archived_at' => $archivedAt]);
    }

    return $version;
}

function bookletLifecycleBooklet(): OmrQuestionBooklet
{
    return OmrQuestionBooklet::query()->create([
        'institution_id' => Institution::current()->id,
        'is_synthetic' => false,
        'code' => 'LIFECYCLE-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 10)),
        'name' => 'Lifecycle Booklet',
    ]);
}

test('authorized staff can discard a single-version draft and delete parent booklet cleanly', function (): void {
    $booklet = bookletLifecycleBooklet();
    $version = bookletLifecycleVersion($booklet, 1, OmrQuestionBookletVersion::STATUS_DRAFT);

    $this->actingAs($this->staff)
        ->delete(route('setup.exam-materials.booklets.destroy', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.index'))
        ->assertSessionHas('status', 'Booklet draft discarded.');

    expect(OmrQuestionBookletVersion::query()->find($version->id))->toBeNull()
        ->and(OmrQuestionBooklet::query()->find($booklet->id))->toBeNull();

    $audit = AuditEvent::query()->where('event_type', 'omr.booklet_draft_discarded')->firstOrFail();
    expect($audit->payload)->toMatchArray([
        'discarded_version_id' => $version->id,
        'parent_booklet_id' => $booklet->id,
        'parent_booklet_deleted' => true,
    ]);
});

test('authorized staff can discard draft v2 while preserving finalized v1', function (): void {
    $booklet = bookletLifecycleBooklet();
    $finalized = bookletLifecycleVersion($booklet, 1, OmrQuestionBookletVersion::STATUS_FINALIZED, now());
    $draft = bookletLifecycleVersion($booklet, 2, OmrQuestionBookletVersion::STATUS_DRAFT);

    $this->actingAs($this->staff)
        ->delete(route('setup.exam-materials.booklets.destroy', $draft))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id))
        ->assertSessionHas('status', 'Booklet draft discarded.');

    expect(OmrQuestionBookletVersion::query()->find($draft->id))->toBeNull()
        ->and($finalized->fresh()->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($finalized->fresh()->finalized_at)->not->toBeNull()
        ->and(OmrQuestionBooklet::query()->find($booklet->id))->not->toBeNull();
});

test('discarding a draft bound to an exam session is rejected with an error', function (): void {
    $booklet = bookletLifecycleBooklet();
    $version = bookletLifecycleVersion($booklet, 1, OmrQuestionBookletVersion::STATUS_DRAFT);
    ExamSession::factory()->create(['booklet_version_id' => $version->id]);

    $this->actingAs($this->staff)->withoutExceptionHandling();

    expect(fn () => $this->delete(route('setup.exam-materials.booklets.destroy', $version)))
        ->toThrow(RuntimeException::class, 'This draft Booklet is in use and cannot be discarded.');

    expect($version->fresh())->not->toBeNull()
        ->and($booklet->fresh())->not->toBeNull();
});

test('archive and restore keep a finalized Booklet key and session pin unchanged', function (): void {
    $booklet = bookletLifecycleBooklet();
    $version = bookletLifecycleVersion($booklet, 1, OmrQuestionBookletVersion::STATUS_FINALIZED, now());
    $session = ExamSession::factory()->create(['booklet_version_id' => $version->id]);
    $answerKeyHash = $version->answer_key_hash;

    $this->actingAs($this->staff)
        ->from(route('setup.exam-materials.booklets.show', $booklet->id))
        ->post(route('setup.exam-materials.booklets.archive', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id))
        ->assertSessionHas('status', 'Booklet version archived.');

    expect($version->fresh()->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($version->fresh()->archived_at)->not->toBeNull()
        ->and($version->fresh()->answer_key_hash)->toBe($answerKeyHash)
        ->and($session->fresh()->booklet_version_id)->toBe($version->id);
    $projection = app(BookletWorkspaceQuery::class)->forBooklet($booklet, $this->staff, false);
    expect($projection)->toMatchArray([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'lifecycle_status' => 'archived',
        'can_archive' => false,
        'can_restore' => true,
    ]);
    $this->from(route('setup.exam-materials.booklets.show', $booklet->id))
        ->post(route('setup.exam-materials.booklets.restore', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id))
        ->assertSessionHas('status', 'Booklet version restored.');

    expect($version->fresh()->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($version->fresh()->archived_at)->toBeNull()
        ->and($version->fresh()->answer_key_hash)->toBe($answerKeyHash)
        ->and($session->fresh()->booklet_version_id)->toBe($version->id);
    expect(AuditEvent::query()->whereIn('event_type', [
        'omr.booklet_version_archived',
        'omr.booklet_version_restored',
    ])->count())->toBe(2);
});

test('authorized staff can restore an archived finalized Booklet without reactivating it', function (): void {
    $booklet = bookletLifecycleBooklet();
    $version = bookletLifecycleVersion(
        $booklet,
        1,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
        now(),
        now(),
    );
    $session = ExamSession::factory()->create(['booklet_version_id' => $version->id]);
    $answerKeyHash = $version->answer_key_hash;

    $this->actingAs($this->staff)
        ->from(route('setup.exam-materials.booklets.show', $booklet->id))
        ->post(route('setup.exam-materials.booklets.restore', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id))
        ->assertSessionHas('status', 'Booklet version restored.');

    expect($version->fresh()->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($version->fresh()->archived_at)->toBeNull()
        ->and($version->fresh()->answer_key_hash)->toBe($answerKeyHash)
        ->and($session->fresh()->booklet_version_id)->toBe($version->id);
    $projection = app(BookletWorkspaceQuery::class)->forBooklet($booklet, $this->staff, false);
    expect($projection)->toMatchArray([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'lifecycle_status' => 'official',
        'can_archive' => true,
        'can_restore' => false,
    ]);
});

test('repeating restore leaves the finalized Booklet unchanged', function (): void {
    $booklet = bookletLifecycleBooklet();
    $version = bookletLifecycleVersion(
        $booklet,
        1,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
        now(),
        now(),
    );

    $this->actingAs($this->staff)
        ->from(route('setup.exam-materials.booklets.show', $booklet->id))
        ->post(route('setup.exam-materials.booklets.restore', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id));
    $this->from(route('setup.exam-materials.booklets.show', $booklet->id))
        ->post(route('setup.exam-materials.booklets.restore', $version))
        ->assertRedirect(route('setup.exam-materials.booklets.show', $booklet->id));

    expect($version->fresh()->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($version->fresh()->archived_at)->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'omr.booklet_version_restored')->count())->toBe(1);
});

test('exam session creation and draft update reject archived finalized Booklets', function (): void {
    $booklet = bookletLifecycleBooklet();
    $archived = bookletLifecycleVersion(
        $booklet,
        1,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
        now(),
        now(),
    );
    $cycle = AcademicCycle::query()->firstOrFail();
    $room = Room::query()->where('is_active', true)->firstOrFail();
    $session = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
    ]);
    $startsAt = now()->addDays(2)->toDateTimeString();
    $endsAt = now()->addDays(2)->addHours(2)->toDateTimeString();

    expect(fn () => app(CreateExamSession::class)->handle(
        cycle: $cycle,
        room: $room,
        code: 'LIFECYCLE-CREATE-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)),
        startsAt: $startsAt,
        effectiveEndAt: $endsAt,
        actor: $this->staff,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        bookletVersion: $archived,
    ))->toThrow(RuntimeException::class, 'Archived Booklets cannot be selected for new exam sessions.');

    expect(fn () => app(UpdateDraftExamSession::class)->handle(
        session: $session,
        cycle: $cycle,
        room: $room,
        code: 'LIFECYCLE-UPDATE-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)),
        startsAt: $startsAt,
        effectiveEndAt: $endsAt,
        actor: $this->staff,
        expectedVersion: (int) $session->version,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        bookletVersion: $archived,
    ))->toThrow(RuntimeException::class, 'Archived Booklets cannot be selected for new exam sessions.');

    $options = app(ExamSessionPresenter::class)->bookletOptions();
    expect(collect($options)->pluck('id')->all())->not->toContain($archived->id);
});

test('standalone Booklet library remains available without assessment catalog capability', function (): void {
    $this->staff->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    $this->actingAs($this->staff)
        ->get(route('setup.exam-materials.booklets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/BookletsIndex')
            ->where('kit_context', null)
        );
});

test('kit context keeps its sealed Booklet version selected and read only instead of family latest', function (): void {
    $booklet = bookletLifecycleBooklet();
    $sealedVersion = bookletLifecycleVersion(
        $booklet,
        1,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
    );
    bookletLifecycleVersion(
        $booklet,
        2,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
    );

    $workspace = app(BookletWorkspaceQuery::class)->forBooklet(
        $booklet,
        $this->staff,
        false,
        (int) $sealedVersion->id,
        true,
        true,
    );

    expect($workspace['version_id'])->toBe((int) $sealedVersion->id)
        ->and($workspace['version'])->toBe(1)
        ->and($workspace['read_only'])->toBeTrue()
        ->and($workspace['next_action'])->toBe('view')
        ->and($workspace['can_archive'])->toBeFalse()
        ->and($workspace['can_discard'])->toBeFalse();
});

test('kit context with no selected Booklet version does not fall back to family latest', function (): void {
    $booklet = bookletLifecycleBooklet();
    bookletLifecycleVersion(
        $booklet,
        1,
        OmrQuestionBookletVersion::STATUS_FINALIZED,
    );

    $workspace = app(BookletWorkspaceQuery::class)->forBooklet(
        $booklet,
        $this->staff,
        false,
        null,
        false,
        true,
    );

    expect($workspace['version_id'])->toBeNull()
        ->and($workspace['version'])->toBeNull()
        ->and($workspace['next_action'])->toBe('select_booklet');
});
