<?php

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Queries\ShowAdmissionAttemptQuery;
use App\Communications\Actions\DesignateIntakePublicPolicy;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Communications\Actions\WithdrawPublicPolicy;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Program;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Database\Seeders\PublicIntakeDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
});

afterEach(function (): void {
    $this->travelBack();
});

function intakeAuthorityData(?int $policyId, ?int $revisionId, ?string $digest): AdmissionIntakeData
{
    return new AdmissionIntakeData(
        firstName: 'Authority',
        lastName: 'Applicant',
        birthdate: '2001-01-01',
        sex: 'female',
        contactEmail: 'authority-applicant@example.test',
        applicantType: 'new',
        programPreferenceCodes: ['BSIT'],
        termsAccepted: true,
        publicPolicyId: $policyId,
        publicPolicyRevisionId: $revisionId,
        publicPolicyRevisionDigest: $digest,
    );
}

/**
 * @return array{policy: PublicPolicy, revision: PublicPolicyRevision, digest: string}
 */
function currentIntakeConsent(): array
{
    $policy = PublicPolicy::designatedIntake((int) Institution::current()->getKey())
        ?? throw new RuntimeException('A designated public intake policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated intake policy has no published revision.');

    return ['policy' => $policy, 'revision' => $revision, 'digest' => $revision->contentDigest()];
}

function historicalPrivacyPolicy(): PrivacyPolicy
{
    return PrivacyPolicy::query()->create([
        'title' => 'Historical synthetic privacy notice',
        'content' => 'Historical provenance only.',
        'is_active' => true,
    ]);
}

function submitAuthorityAttempt(
    ?Institution $institution = null,
    ?AcademicCycle $cycle = null,
    ?ApplicationWindow $window = null,
    ?AdmissionIntakeData $intake = null,
): array {
    $institution ??= Institution::current();
    $cycle ??= AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window ??= ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    if ($intake === null) {
        $consent = currentIntakeConsent();
        $intake = intakeAuthorityData(
            (int) $consent['policy']->getKey(),
            (int) $consent['revision']->getKey(),
            $consent['digest'],
        );
    }

    return app(SubmitAdmissionAttempt::class)->handle(
        $institution,
        $cycle,
        $window,
        $intake,
        'test',
    );
}
test('public intake rejects a retired program without writing applicant records', function (): void {
    $consent = currentIntakeConsent();
    $intake = new AdmissionIntakeData(
        firstName: 'Authority',
        lastName: 'Applicant',
        birthdate: '2001-01-01',
        sex: 'female',
        contactEmail: 'retired-program@example.test',
        applicantType: 'new',
        programPreferenceCodes: ['BSN'],
        termsAccepted: true,
        publicPolicyId: (int) $consent['policy']->getKey(),
        publicPolicyRevisionId: (int) $consent['revision']->getKey(),
        publicPolicyRevisionDigest: $consent['digest'],
    );

    expect(fn () => submitAuthorityAttempt(intake: $intake))
        ->toThrow(ValidationException::class);

    expectNoIntakeWrites();
});

function expectNoIntakeWrites(): void
{
    expect(ApplicantIdentity::query()->count())->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe(0)
        ->and(DB::table('applicant_access_challenges')->count())->toBe(0)
        ->and(AuditEvent::query()->where('event_type', 'admission.attempt_submitted')->count())->toBe(0);
}

test('public apply displays and records the current immutable designated policy revision', function (): void {
    $consent = currentIntakeConsent();

    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Apply')
        ->where('page_state', 'ready')
        ->has('window.id')
        ->where('privacy_policy.id', $consent['policy']->id)
        ->where('privacy_policy.revision_id', $consent['revision']->id)
        ->where('privacy_policy.revision_number', $consent['revision']->revision)
        ->where('privacy_policy.digest', $consent['digest'])
        ->where('privacy_policy.title', $consent['revision']->title)
        ->where('privacy_policy.content', $consent['revision']->content)
    );

    $attempt = submitAuthorityAttempt()['attempt'];
    expect($attempt->privacy_policy_id)->toBeNull()
        ->and((int) $attempt->public_policy_id)->toBe((int) $consent['policy']->id)
        ->and($attempt->payload['consent'])->toMatchArray([
            'source' => 'public_policy',
            'policy_id' => (int) $consent['policy']->id,
            'revision_id' => (int) $consent['revision']->id,
            'revision' => (int) $consent['revision']->revision,
            'digest' => $consent['digest'],
        ])
        ->and($attempt->status)->toBe('pending');
});
test('public apply consumes a fresh opening-alert email prefill and ignores an expired one', function (): void {
    $this->withSession([
        'application_opening_prefill' => [
            'email' => 'opening-alert@example.test',
            'expires_at' => time() + 900,
        ],
    ])->get(route('apply.form'))
        ->assertOk()
        ->assertSessionMissing('application_opening_prefill')
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->where('prefilled_email', 'opening-alert@example.test')
        );

    $this->withSession([
        'application_opening_prefill' => [
            'email' => 'expired@example.test',
            'expires_at' => time() - 1,
        ],
    ])->get(route('apply.form'))
        ->assertOk()
        ->assertSessionMissing('application_opening_prefill')
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->where('prefilled_email', '')
        );
});


test('scoped intake demo seed exposes the real form and a pending applicant login', function (): void {
    (new PublicIntakeDemoSeeder)->run();
    $pending = config('seed.applicants.pending');

    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('page_state', 'ready')
        ->has('privacy_policy.revision_id')
        ->has('privacy_policy.digest')
    );
    $this->post(route('applicant.login.store'), [
        'access_key' => $pending['access_key'],
        'birthdate' => $pending['birthdate'],
    ])->assertRedirect(route('applicant.portal'));
});

test('an expired intake is empty on GET and fails closed on a stale POST', function (): void {
    $this->travelTo(Carbon::parse('2027-01-01 12:00:00'));

    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Apply')
        ->where('page_state', 'empty')
        ->where('window', null)
    );

    expect(fn () => submitAuthorityAttempt())->toThrow(ValidationException::class);
    expectNoIntakeWrites();
});

test('inactive and foreign cycles cannot be submitted through the ordinary Action', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->update(['is_active' => false]);
    expect(fn () => submitAuthorityAttempt(cycle: $cycle))->toThrow(ValidationException::class);

    $cycle->update(['is_active' => true, 'opens_on' => '2026-09-26']);
    expect(fn () => submitAuthorityAttempt(cycle: $cycle))->toThrow(ValidationException::class);

    $otherInstitution = Institution::query()->create(['code' => 'OTHER-INST', 'name' => 'Other']);
    $cycle->update(['institution_id' => $otherInstitution->id, 'opens_on' => '2026-01-01']);
    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('page_state', 'empty')
        ->where('window', null)
    );
    expect(fn () => submitAuthorityAttempt(cycle: $cycle))->toThrow(ValidationException::class);
    expectNoIntakeWrites();
});

test('a closed, future, expired or foreign window cannot be submitted through the ordinary Action', function (): void {
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    $window->update(['status' => 'closed']);
    expect(fn () => submitAuthorityAttempt(window: $window))->toThrow(ValidationException::class);

    $window->update(['status' => 'published', 'opens_at' => '2026-10-01 00:00:00']);
    expect(fn () => submitAuthorityAttempt(window: $window))->toThrow(ValidationException::class);

    $window->update(['opens_at' => '2026-01-01 00:00:00', 'closes_at' => '2026-09-24 23:59:59']);
    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('page_state', 'empty')
        ->where('window', null)
    );
    expect(fn () => submitAuthorityAttempt(window: $window))->toThrow(ValidationException::class);

    $otherInstitution = Institution::query()->create(['code' => 'OTHER-INST', 'name' => 'Other']);
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'OTHER-CYCLE',
        'name' => 'Other Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);
    $window->update(['closes_at' => '2026-12-31 23:59:59', 'academic_cycle_id' => $otherCycle->id]);
    expect(fn () => submitAuthorityAttempt(window: $window))->toThrow(ValidationException::class);
    expectNoIntakeWrites();
});

test('missing, foreign or stale consent bindings fail before any intake write', function (): void {
    $consent = currentIntakeConsent();
    $policyId = (int) $consent['policy']->getKey();
    $revisionId = (int) $consent['revision']->getKey();
    $digest = $consent['digest'];

    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData(null, null, null)))
        ->toThrow(ValidationException::class);
    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData($policyId + 999, $revisionId, $digest)))
        ->toThrow(ValidationException::class);
    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData($policyId, $revisionId + 999, $digest)))
        ->toThrow(ValidationException::class);
    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData($policyId, $revisionId, str_repeat('0', 64))))
        ->toThrow(ValidationException::class);

    expectNoIntakeWrites();
});

test('draft saves preserve consent while publishing a successor invalidates old forms even with identical text', function (): void {
    $consent = currentIntakeConsent();
    $policy = $consent['policy'];
    $actor = App\Models\User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $saveId = (string) Str::uuid();
    $saved = app(SavePublicPolicy::class)->handle($actor, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => (int) $policy->revision,
        'request_id' => $saveId,
        'title' => $consent['revision']->title,
        'content' => $consent['revision']->content,
    ]);

    $draftState = currentIntakeConsent();
    expect((int) $draftState['revision']->getKey())->toBe((int) $consent['revision']->getKey())
        ->and($draftState['digest'])->toBe($consent['digest']);
    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData(
        (int) $consent['policy']->getKey(),
        (int) $consent['revision']->getKey(),
        $consent['digest'],
    )))->not->toThrow(ValidationException::class);

    app(PublishPublicPolicy::class)->handle($actor, $saved, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $saved->revision,
    ]);
    $successor = currentIntakeConsent();
    expect((int) $successor['revision']->getKey())->not->toBe((int) $consent['revision']->getKey())
        ->and($successor['digest'])->toBe($consent['digest']);
    $identityCount = ApplicantIdentity::query()->count();
    $attemptCount = AdmissionAttempt::query()->count();
    $challengeCount = DB::table('applicant_access_challenges')->count();
    $auditCount = AuditEvent::query()->where('event_type', 'admission.attempt_submitted')->count();

    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData(
        (int) $consent['policy']->getKey(),
        (int) $consent['revision']->getKey(),
        $consent['digest'],
    )))->toThrow(ValidationException::class);
    expect(ApplicantIdentity::query()->count())->toBe($identityCount)
        ->and(AdmissionAttempt::query()->count())->toBe($attemptCount)
        ->and(DB::table('applicant_access_challenges')->count())->toBe($challengeCount)
        ->and(AuditEvent::query()->where('event_type', 'admission.attempt_submitted')->count())->toBe($auditCount);
});

test('public POST does not replace a stale consent ID or revision digest with the latest policy', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    $consent = currentIntakeConsent();

    $this->post(route('apply.submit'), [
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'public_policy_id' => $consent['policy']->id,
        'public_policy_revision_id' => $consent['revision']->id,
        'public_policy_revision_digest' => str_repeat('0', 64),
        'first_name' => 'Authority',
        'last_name' => 'Applicant',
        'email' => 'authority-applicant@example.test',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
        'applicant_type' => 'new',
        'program_preference_1' => 'BSIT',
        'terms_accepted' => true,
        'last_school_enrolled' => 'Sample SHS',
        'strand' => 'STEM',
        'grades' => ['GWA' => '1.75', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
    ])->assertSessionHasErrors(['public_policy_revision_digest']);

    expectNoIntakeWrites();
});

test('only a same-institution general designated published privacy revision can open intake', function (): void {
    $consent = currentIntakeConsent();
    $institutionId = (int) Institution::current()->getKey();
    $policy = $consent['policy'];
    $original = $policy->getAttributes();
    $otherInstitution = Institution::query()->create(['code' => 'OTHER-POLICY-INST', 'name' => 'Other institution']);
    $program = Program::query()->where('institution_id', $institutionId)->where('code', 'BSIT')->firstOrFail();

    foreach ([
        ['institution_id' => $otherInstitution->getKey()],
        ['kind' => 'policy'],
        ['program_id' => $program->getKey()],
        ['status' => 'draft'],
        ['status' => 'withdrawn'],
        ['is_designated_intake' => false],
        ['published_revision_id' => null],
    ] as $invalidState) {
        $policy->forceFill($invalidState)->save();

        expect(PublicPolicy::designatedIntake($institutionId))->toBeNull();
        expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData(
            (int) $consent['policy']->getKey(),
            (int) $consent['revision']->getKey(),
            $consent['digest'],
        )))->toThrow(ValidationException::class);

        $policy->forceFill($original)->save();
    }

    expectNoIntakeWrites();
});

test('historical active PrivacyPolicy cannot reopen intake after a selected PublicPolicy is withdrawn', function (): void {
    $consent = currentIntakeConsent();
    $actor = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $withdrawn = app(WithdrawPublicPolicy::class)->handle($actor, $consent['policy'], [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $consent['policy']->revision,
        'reason' => 'Synthetic test withdrawal closes applicant intake.',
    ]);
    $legacyPolicy = historicalPrivacyPolicy();

    expect($withdrawn->is_designated_intake)->toBeTrue()
        ->and(PublicPolicy::currentIntakeSelection((int) $withdrawn->institution_id)?->getKey())->toBe($withdrawn->getKey())
        ->and(PublicPolicy::designatedIntake((int) $withdrawn->institution_id))->toBeNull()
        ->and(PrivacyPolicy::query()->whereKey($legacyPolicy->getKey())->where('is_active', true)->exists())->toBeTrue();
    $this->get(route('apply.form'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Apply')
        ->where('page_state', 'empty')
        ->where('window', null)
        ->where('privacy_policy', null)
    );

    expect(fn () => submitAuthorityAttempt(intake: intakeAuthorityData(
        (int) $consent['policy']->getKey(),
        (int) $consent['revision']->getKey(),
        $consent['digest'],
    )))->toThrow(ValidationException::class);
    expectNoIntakeWrites();
});

test('non-colliding PublicPolicy IDs submit against the public table without writing the legacy FK', function (): void {
    $consent = currentIntakeConsent();
    $actor = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $draft = app(SavePublicPolicy::class)->handle($actor, [
        'publisher_key' => 'office:registrar',
        'program_id' => null,
        'kind' => 'privacy',
        'request_id' => (string) Str::uuid(),
        'title' => '[DEMO] Non-colliding consent ID',
        'content' => 'Synthetic consent test text.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($actor, $draft, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $draft->revision,
    ]);
    $policy = app(DesignateIntakePublicPolicy::class)->handle($actor, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
        'expected_intake_policy_id' => (int) $consent['policy']->getKey(),
    ]);
    $revision = $policy->publishedRevision()->firstOrFail();

    expect(PrivacyPolicy::query()->whereKey($policy->getKey())->exists())->toBeFalse();

    $this->post(route('apply.submit'), [
        'academic_cycle_id' => AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->value('id'),
        'application_window_id' => ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->value('id'),
        'public_policy_id' => $policy->getKey(),
        'public_policy_revision_id' => $revision->getKey(),
        'public_policy_revision_digest' => $revision->contentDigest(),
        'first_name' => 'Non-Colliding',
        'last_name' => 'Applicant',
        'email' => 'non-colliding@example.test',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
        'applicant_type' => 'new',
        'program_preference_1' => 'BSIT',
        'last_school_enrolled' => 'Synthetic Senior High School',
        'strand' => 'STEM',
        'terms_accepted' => true,
        'grades' => ['GWA' => '1.75', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
    ])->assertRedirect(route('apply.confirmation'));

    $attempt = AdmissionAttempt::query()->whereHas('identity', fn ($query) => $query->where('email', 'non-colliding@example.test'))->firstOrFail();
    expect((int) $attempt->public_policy_id)->toBe((int) $policy->getKey())
        ->and($attempt->privacy_policy_id)->toBeNull()
        ->and($attempt->payload['consent']['source'])->toBe('public_policy')
        ->and($attempt->payload['consent']['revision_id'])->toBe((int) $revision->getKey());
});

test('historical consent detail resolves only its recorded source and never substitutes the current revision', function (): void {
    $consent = currentIntakeConsent();
    $attempt = submitAuthorityAttempt()['attempt'];
    $actor = App\Models\User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $saved = app(SavePublicPolicy::class)->handle($actor, [
        'policy_id' => (int) $consent['policy']->getKey(),
        'expected_revision' => (int) $consent['policy']->revision,
        'request_id' => (string) Str::uuid(),
        'title' => 'Successor privacy notice',
        'content' => 'A different published successor.',
    ]);
    app(PublishPublicPolicy::class)->handle($actor, $saved, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $saved->revision,
    ]);

    $detail = app(ShowAdmissionAttemptQuery::class)->handle($attempt->fresh());
    expect($detail['consent'])->toMatchArray([
        'source' => 'public_policy',
        'policy_id' => (int) $consent['policy']->getKey(),
        'revision_id' => (int) $consent['revision']->getKey(),
        'revision' => (int) $consent['revision']->revision,
        'digest' => $consent['digest'],
        'title' => $consent['revision']->title,
        'revision_known' => true,
    ]);

    $legacyPolicy = historicalPrivacyPolicy();
    $attempt->forceFill([
        'privacy_policy_id' => $legacyPolicy->getKey(),
        'payload' => ['terms_accepted' => true],
    ])->save();
    $ambiguous = app(ShowAdmissionAttemptQuery::class)->handle($attempt->fresh())['consent'];
    expect($ambiguous)->toMatchArray([
        'source' => 'unverified',
        'policy_id' => null,
        'legacy_policy_id' => (int) $legacyPolicy->getKey(),
        'public_policy_id' => (int) $consent['policy']->getKey(),
        'revision_id' => null,
        'title' => null,
        'revision_known' => false,
    ]);

    $attempt->forceFill([
        'privacy_policy_id' => null,
        'payload' => ['terms_accepted' => true],
    ])->save();
    $unknown = app(ShowAdmissionAttemptQuery::class)->handle($attempt->fresh())['consent'];
    expect($unknown)->toMatchArray([
        'source' => 'public_policy',
        'policy_id' => (int) $consent['policy']->getKey(),
        'revision_id' => null,
        'revision' => null,
        'digest' => null,
        'title' => null,
        'revision_known' => false,
    ]);

    $attempt->forceFill([
        'privacy_policy_id' => $legacyPolicy->getKey(),
        'public_policy_id' => null,
        'payload' => [],
    ])->save();
    $legacy = app(ShowAdmissionAttemptQuery::class)->handle($attempt->fresh())['consent'];
    expect($legacy)->toMatchArray([
        'source' => 'legacy_privacy_policy',
        'policy_id' => (int) $legacyPolicy->getKey(),
        'revision_id' => null,
        'revision' => null,
        'digest' => null,
        'title' => $legacyPolicy->title,
        'revision_known' => false,
    ]);
});

test('nullable public policy linkage migration roundtrip preserves old PrivacyPolicy provenance without backfill', function (): void {
    $originalConnection = DB::getDefaultConnection();
    $testConnection = 'public_intake_migration_roundtrip';
    config([
        "database.connections.{$testConnection}" => array_merge(
            config("database.connections.{$originalConnection}"),
            ['database' => ':memory:'],
        ),
    ]);
    DB::purge($testConnection);
    DB::setDefaultConnection($testConnection);

    try {
        Schema::create('public_policies', function ($table): void {
            $table->id();
        });
        Schema::create('privacy_policies', function ($table): void {
            $table->id();
        });
        Schema::create('admission_attempts', function ($table): void {
            $table->id();
            $table->foreignId('privacy_policy_id')->nullable();
            $table->foreignId('public_policy_id')->nullable()->constrained('public_policies')->nullOnDelete();
        });

        $legacyPolicyId = DB::table('privacy_policies')->insertGetId([]);
        $attemptId = DB::table('admission_attempts')->insertGetId([
            'privacy_policy_id' => $legacyPolicyId,
            'public_policy_id' => null,
        ]);
        $migration = require database_path('migrations/2026_10_08_020000_add_public_policy_id_to_admission_attempts_table.php');

        $migration->down();
        expect(Schema::hasColumn('admission_attempts', 'public_policy_id'))->toBeFalse()
            ->and(DB::table('admission_attempts')->where('id', $attemptId)->value('privacy_policy_id'))
            ->toBe($legacyPolicyId);

        $migration->up();
        expect(Schema::hasColumn('admission_attempts', 'public_policy_id'))->toBeTrue()
            ->and(DB::table('admission_attempts')->where('id', $attemptId)->value('privacy_policy_id'))
            ->toBe($legacyPolicyId)
            ->and(DB::table('admission_attempts')->where('id', $attemptId)->value('public_policy_id'))
            ->toBeNull();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge($testConnection);
    }
});

test('policy kind history migration leaves unknown rows null and rejects duplicate designation flags', function (): void {
    $selected = currentIntakeConsent()['policy'];
    $actor = App\Models\User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $second = app(SavePublicPolicy::class)->handle($actor, [
        'publisher_key' => 'office:registrar',
        'program_id' => null,
        'kind' => 'privacy',
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic second migration privacy policy',
        'content' => 'Synthetic published content for migration safety.',
    ]);
    $second = app(PublishPublicPolicy::class)->handle($actor, $second, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $second->revision,
    ]);
    $migration = require database_path('migrations/2026_10_08_024400_add_kind_to_public_policy_revisions_and_intake_designation_index.php');
    $migration->down();
    expect(Schema::hasColumn('public_policy_revisions', 'kind'))->toBeFalse();

    Illuminate\Support\Facades\DB::table('public_policies')->where('id', $second->getKey())->update(['is_designated_intake' => true]);
    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'duplicate designations exist')
        ->and(Schema::hasColumn('public_policy_revisions', 'kind'))->toBeFalse();

    Illuminate\Support\Facades\DB::table('public_policies')->where('id', $second->getKey())->update(['is_designated_intake' => false]);
    $migration->up();
    expect(Schema::hasColumn('public_policy_revisions', 'kind'))->toBeTrue()
        ->and(Illuminate\Support\Facades\DB::table('public_policy_revisions')->where('id', $selected->published_revision_id)->value('kind'))->toBeNull();

    expect(fn () => Illuminate\Support\Facades\DB::table('public_policies')->where('id', $second->getKey())->update(['is_designated_intake' => true]))
        ->toThrow(QueryException::class);
});

test('local consent fixture seeding leaves an existing designated publication untouched', function (): void {
    $consent = currentIntakeConsent();
    $before = [
        'id' => (int) $consent['policy']->getKey(),
        'title' => $consent['policy']->title,
        'content' => $consent['policy']->content,
        'published_revision_id' => (int) $consent['policy']->published_revision_id,
        'revision_count' => $consent['policy']->revisions()->count(),
    ];

    (new PublicIntakeConsentDemoSeeder)->run();

    $after = currentIntakeConsent();
    expect((int) $after['policy']->getKey())->toBe($before['id'])
        ->and($after['policy']->title)->toBe($before['title'])
        ->and($after['policy']->content)->toBe($before['content'])
        ->and((int) $after['policy']->published_revision_id)->toBe($before['published_revision_id'])
        ->and($after['policy']->revisions()->count())->toBe($before['revision_count']);
});

test('fixture seeder does not reopen or replace an existing withdrawn designation', function (): void {
    $consent = currentIntakeConsent();
    $policy = $consent['policy'];
    $actor = App\Models\User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $policyCount = PublicPolicy::query()->where('institution_id', $policy->institution_id)->count();
    $publishedRevisionId = (int) $policy->published_revision_id;

    $withdrawn = app(WithdrawPublicPolicy::class)->handle($actor, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
        'reason' => 'Synthetic withdrawal fixture; preserve the designation hold.',
    ]);

    expect($withdrawn->status)->toBe('withdrawn')
        ->and((int) $withdrawn->published_revision_id)->toBe($publishedRevisionId)
        ->and($withdrawn->is_designated_intake)->toBeTrue();
    expect(fn () => (new PublicIntakeConsentDemoSeeder)->run())->toThrow(RuntimeException::class);

    $designation = PublicPolicy::query()
        ->where('institution_id', $policy->institution_id)
        ->where('is_designated_intake', true)
        ->firstOrFail();
    expect(PublicPolicy::query()->where('institution_id', $policy->institution_id)->count())->toBe($policyCount)
        ->and((int) $designation->getKey())->toBe((int) $policy->getKey())
        ->and($designation->status)->toBe('withdrawn')
        ->and((int) $designation->published_revision_id)->toBe($publishedRevisionId);
});
