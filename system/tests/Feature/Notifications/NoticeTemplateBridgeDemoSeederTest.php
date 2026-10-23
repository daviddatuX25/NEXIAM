<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantAccessKey;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Import\DocxPackageInspector;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Actions\DiscardCommunicationTemplateDraft;
use App\Notifications\Templates\Actions\PublishCommunicationTemplateVersion;
use App\Notifications\Templates\Actions\RenderCommunicationTemplatePreview;
use App\Notifications\Templates\Actions\ReviewCommunicationTemplateImport;
use App\Notifications\Templates\Actions\SaveCommunicationTemplateDraft;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Database\Seeders\NoticeTemplateBridgeDemoSeeder;
use Database\Seeders\NoticeTemplateBridgeWordFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    Mail::fake();
});

it('prepares two scoped idle fixtures and a real unreviewed import without workflow delivery', function (): void {
    $this->freezeTime();
    $active = AcademicCycle::active()->getAttributes();
    $deliveryCount = NotificationDelivery::query()->count();
    (new NoticeTemplateBridgeDemoSeeder)->run();

    $session = ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)
        ->with(['academicCycle', 'room', 'assignments.admissionAttempt.identity'])->firstOrFail();
    $policy = ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot);
    expect($session->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($session->starts_at->equalTo(now()->addHours(48)->startOfMinute()))->toBeTrue()
        ->and($session->academicCycle->is_active)->toBeFalse()
        ->and($session->academicCycle->code)->toBe(NoticeTemplateBridgeDemoSeeder::CYCLE_CODE)
        ->and($session->assignments)->toHaveCount(2)
        ->and($session->notification_policy_snapshot['session_version'])->toBe($session->version)
        ->and($policy->reminderOffsetsMinutes)->toBe([1440])
        ->and($policy->reminderEmailEnabled)->toBeTrue()
        ->and($policy->publicationEmailEnabled)->toBeFalse()
        ->and(AcademicCycle::active()->getAttributes())->toBe($active);

    foreach ($session->assignments as $assignment) {
        expect($assignment->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
            ->and($assignment->version)->toBe(1)
            ->and($assignment->admissionAttempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
            ->and((int) $assignment->admissionAttempt->institution_id)->toBe((int) $session->academicCycle->institution_id)
            ->and((int) $assignment->admissionAttempt->identity->institution_id)->toBe((int) $session->academicCycle->institution_id);
    }
    expect($session->assignments->pluck('admissionAttempt.identity.email')->sort()->values()->all())->toBe(array_column(NoticeTemplateBridgeDemoSeeder::APPLICANTS, 'email'));

    $family = CommunicationTemplate::query()->where('institution_id', $session->academicCycle->institution_id)
        ->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $draft = $family->draft()->firstOrFail();
    expect($draft->stage)->toBe(2)
        ->and($draft->document_mode)->toBe('none')
        ->and($draft->document_asset_id)->toBeNull()
        ->and($draft->import_provenance['review_acknowledgment'])->toBeNull()
        ->and($family->versions()->where('content_schema_version', 'notice-content-2')->count())->toBe(0)
        ->and($family->draft()->count())->toBe(1)
        ->and($family->receipts()->count())->toBe(2)
        ->and(array_column($draft->email_blueprint['blocks'], 'type'))->toContain('heading', 'paragraph', 'list', 'table', 'image');
    $serialized = json_encode($draft->email_blueprint, JSON_THROW_ON_ERROR);
    expect($serialized)->toContain('applicant_name', 'session_code', 'exam_date', 'exam_time', 'room_name', 'portal_url')
        ->not->toContain('building', 'seat_number');

    $source = CommunicationTemplateDocument::query()->findOrFail($draft->import_provenance['source_asset_id']);
    $report = CommunicationTemplateDocument::query()->findOrFail($draft->import_provenance['report_id']);
    $imageId = collect($draft->email_blueprint['blocks'])->firstWhere('type', 'image')['asset_id'];
    $image = CommunicationTemplateDocument::query()->findOrFail($imageId);
    foreach ([$source, $report, $image] as $asset) {
        expect((int) $asset->institution_id)->toBe((int) $family->institution_id)
            ->and((string) $asset->communication_template_id)->toBe((string) $family->id)
            ->and($asset->checksum)->toBe(hash('sha256', Storage::disk('local')->get($asset->storage_path)));
    }
    expect($source->checksum)->toBe(hash_file('sha256', database_path(NoticeTemplateBridgeWordFixture::RELATIVE_PATH)))
        ->and($report->checksum)->toBe($draft->import_provenance['report_sha256'])
        ->and($image->mime_type)->toBe('image/png')
        ->and(getimagesizefromstring(Storage::disk('local')->get($image->storage_path))[0])->toBe(240)
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('keeps generated bytes, seeded identities, draft, receipts and schedule unchanged on repeated runs', function (): void {
    $this->freezeTime();
    $seeder = new NoticeTemplateBridgeDemoSeeder;
    $seeder->run();
    $family = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $draft = $family->draft()->firstOrFail()->getAttributes();
    $familyState = $family->getAttributes();
    $session = ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->firstOrFail()->getAttributes();
    $receiptIds = $family->receipts()->pluck('id')->all();
    $assetIds = CommunicationTemplateDocument::query()->pluck('id')->all();
    $accessRows = ApplicantAccessChallenge::query()->where('request_key', 'like', 'notice-bridge-demo-access-%')->get()->toArray();
    $path = database_path(NoticeTemplateBridgeWordFixture::RELATIVE_PATH);
    $hash = hash_file('sha256', $path);
    $inspection = app(DocxPackageInspector::class)->inspect($path);
    expect($inspection->sha256)->toBe($hash)
        ->and(filesize($path))->toBeLessThan(10485760);

    $this->travel(3)->hours();
    $seeder->run();
    expect(hash_file('sha256', $path))->toBe($hash)
        ->and($family->fresh()->getAttributes())->toBe($familyState)
        ->and($family->draft()->firstOrFail()->getAttributes())->toBe($draft)
        ->and(ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->firstOrFail()->getAttributes())->toBe($session)
        ->and($family->receipts()->pluck('id')->all())->toBe($receiptIds)
        ->and(CommunicationTemplateDocument::query()->pluck('id')->all())->toBe($assetIds)
        ->and(ApplicantAccessChallenge::query()->where('request_key', 'like', 'notice-bridge-demo-access-%')->get()->toArray())->toBe($accessRows)
        ->and(ExamAssignment::query()->where('exam_session_id', $session['id'])->count())->toBe(2);
});

it('leaves an existing staff authored canonical draft and unrelated cycle untouched', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution, $guidance, 'examination');
    $family = CommunicationTemplate::query()->where('institution_id', $institution->id)->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    app(SaveCommunicationTemplateDraft::class)->save($family, [
        'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => null, 'idempotency_key' => 'staff-existing-reminder-draft', 'stage' => 2,
        'state' => ['subject' => 'Staff authored subject', 'body' => 'Staff authored caption', 'action_label' => null,
            'action_route' => null, 'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Do not replace my work.']]],
            'document_mode' => 'none', 'document_asset_id' => null, 'builder_json' => null, 'paper_size' => null,
            'orientation' => null, 'document_source_drafts' => null, 'import_provenance' => null],
    ], $guidance);
    $draft = $family->draft()->firstOrFail()->getAttributes();
    $familyState = $family->fresh()->getAttributes();
    $activeState = AcademicCycle::active()->getAttributes();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    expect($family->draft()->firstOrFail()->getAttributes())->toBe($draft)
        ->and($family->fresh()->getAttributes())->toBe($familyState)
        ->and(AcademicCycle::active()->getAttributes())->toBe($activeState)
        ->and(CommunicationTemplateDocument::query()->where('communication_template_id', $family->id)->count())->toBe(0)
        ->and(CommunicationTemplateReceipt::query()->where('idempotency_key', NoticeTemplateBridgeDemoSeeder::CREATE_KEY)->count())->toBe(0);
});

it('does not resurrect a seeded draft after an explicit staff discard', function (): void {
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $family = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $draft = $family->draft()->firstOrFail();
    app(DiscardCommunicationTemplateDraft::class)->discard($family, [
        'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'draft_id' => $draft->id, 'idempotency_key' => 'staff-demo-discard',
    ], $guidance);
    $familyState = $family->fresh()->getAttributes();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    expect($family->draft()->count())->toBe(0)
        ->and($family->fresh()->getAttributes())->toBe($familyState);
});

it('provides independent access-key and birthdate logins without seeded applicant passwords', function (): void {
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $userIds = [];
    foreach (NoticeTemplateBridgeDemoSeeder::APPLICANTS as $applicant) {
        expect(User::query()->where('email', $applicant['email'])->exists())->toBeFalse();
        $challenge = ApplicantAccessChallenge::query()->where('request_key', 'notice-bridge-demo-access-'.$applicant['reference'])->firstOrFail();
        expect($challenge->token_hash)->toBe(ApplicantAccessKey::hash($applicant['access_key']));
        $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_key'], $applicant['birthdate']);
        $account = ApplicantAccount::query()->where('user_id', $user->id)->firstOrFail();
        $attempt = AdmissionAttempt::query()->where('reference_code', $applicant['reference'])->firstOrFail();
        expect((int) $account->applicant_identity_id)->toBe((int) $attempt->applicant_identity_id)
            ->and($user->email)->toBe($applicant['email']);
        $userIds[] = $user->id;
    }
    expect(array_unique($userIds))->toHaveCount(2);
    expect(fn () => app(AuthenticateApplicantWithAccessKey::class)->handle(
        NoticeTemplateBridgeDemoSeeder::APPLICANTS[0]['access_key'], NoticeTemplateBridgeDemoSeeder::APPLICANTS[1]['birthdate'],
    ))->toThrow(RuntimeException::class, 'Invalid access key or birthdate.');
});

it('keeps the idle fixture outside the real reminder scan and refuses preparation before reviewed publication', function (): void {
    $this->freezeTime();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $session = ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->firstOrFail();
    $before = $session->getAttributes();
    $deliveryCount = NotificationDelivery::query()->count();
    $this->artisan('notifications:send-exam-reminders', ['--window' => '24h'])->assertSuccessful();
    $this->artisan('demo:prepare-notice-bridge-reminder', ['--template-version' => 2])->assertFailed();
    expect($session->fresh()->getAttributes())->toBe($before)
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('arms only the dedicated fixture after real service publication and preserves its generation on replay', function (): void {
    $this->freezeTime();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $family = publishNoticeBridgePreparationTestVersion();
    $deliveryCount = NotificationDelivery::query()->count();
    $this->artisan('demo:prepare-notice-bridge-reminder', ['--template-version' => $family->active_version])->assertSuccessful();
    $session = ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->firstOrFail();
    expect($session->starts_at->getTimestamp())->toBe(now()->addHours(24)->getTimestamp())
        ->and($session->version)->toBe(2)
        ->and($session->notification_policy_snapshot['session_version'])->toBe(2)
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
    $armed = $session->getAttributes();
    $this->travel(5)->minutes();
    $this->artisan('demo:prepare-notice-bridge-reminder', ['--template-version' => $family->active_version])->assertSuccessful();
    expect($session->fresh()->getAttributes())->toBe($armed);
    $this->artisan('demo:prepare-notice-bridge-reminder', ['--template-version' => $family->active_version + 1])->assertFailed();
    expect($session->fresh()->getAttributes())->toBe($armed);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

function publishNoticeBridgePreparationTestVersion(): CommunicationTemplate
{
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $family = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $draft = $family->draft()->firstOrFail();
    app(SaveCommunicationTemplateDraft::class)->save($family, [
        'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'idempotency_key' => 'preparation-test-stage-check',
        'stage' => 3, 'state' => CommunicationTemplateState::state($draft),
    ], $actor);
    $family->refresh();
    $draft = $family->draft()->firstOrFail();
    $resolutions = [];
    foreach ($draft->import_provenance['findings'] as $finding) {
        $resolutions[$finding['id']] = ['disposition' => 'acknowledged'];
    }
    app(ReviewCommunicationTemplateImport::class)->handle($family, $actor, [
        'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'idempotency_key' => 'preparation-test-review',
        'report_id' => $draft->import_provenance['report_id'], 'report_sha256' => $draft->import_provenance['report_sha256'],
        'email_blueprint_sha256' => CommunicationTemplateState::emailDigest($draft->email_blueprint), 'resolutions' => $resolutions,
    ]);
    $family->refresh();
    $draft = $family->draft()->firstOrFail();
    $guards = ['base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'content_sha256' => $draft->content_sha256];
    $receipts = [];
    foreach (['email', 'portal'] as $view) {
        $result = app(RenderCommunicationTemplatePreview::class)->execute($family, [...$guards, 'view' => $view], $actor);
        $receipts[$view] = $result['preview_receipt'];
    }
    app(PublishCommunicationTemplateVersion::class)->publish($family, [
        ...$guards, 'idempotency_key' => 'preparation-test-publish', 'preview_receipts' => $receipts,
    ], $actor);

    return $family->fresh();
}

it('creates verified onboarded author and assessment-only staff fixtures without resetting passwords or existing staff', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $counselorState = $counselor->getAttributes();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $reader = User::query()->where('email', NoticeTemplateBridgeDemoSeeder::ASSESSMENT_EMAIL)->firstOrFail();
    expect(Hash::check('password', $reader->password))->toBeTrue()
        ->and($reader->email_verified_at)->not->toBeNull()
        ->and($reader->roles()->count())->toBe(0)
        ->and($reader->getAllPermissions()->pluck('name')->all())->toBe([CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG])
        ->and($reader->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE))->toBeFalse()
        ->and($reader->staffProfile->office)->toBe('guidance')
        ->and($reader->staffProfile->isActive())->toBeTrue()
        ->and($reader->authenticationState->isOnboarded())->toBeTrue()
        ->and($reader->authenticationState->must_change_password)->toBeFalse()
        ->and($reader->authenticationState->mfa_required)->toBeFalse();
    $author = User::query()->where('email', NoticeTemplateBridgeDemoSeeder::AUTHOR_EMAIL)->firstOrFail();
    $scope = app(CommunicationTemplateScope::class);
    expect(Hash::check('password', $author->password))->toBeTrue()
        ->and($author->email_verified_at)->not->toBeNull()
        ->and($author->roles()->count())->toBe(0)
        ->and($author->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE])
        ->and($author->authenticationState->isOnboarded())->toBeTrue()
        ->and($author->authenticationState->must_change_password)->toBeFalse()
        ->and($author->authenticationState->mfa_required)->toBeFalse()
        ->and($scope->canAccessFullSource($author, 'examination'))->toBeTrue()
        ->and($scope->canAccessFullSource($author, 'results'))->toBeTrue();
    $author->update(['password' => 'Changed-Own-Author-Password-2026']);
    $authorState = $author->getAttributes();
    $reader->update(['password' => 'Changed-Own-Demo-Password-2026']);
    $changed = $reader->getAttributes();
    $profile = $reader->staffProfile->getAttributes();
    $authentication = $reader->authenticationState->getAttributes();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    expect($reader->fresh()->getAttributes())->toBe($changed)
        ->and($reader->staffProfile->fresh()->getAttributes())->toBe($profile)
        ->and($reader->authenticationState->fresh()->getAttributes())->toBe($authentication)
        ->and($counselor->fresh()->getAttributes())->toBe($counselorState)
        ->and($author->fresh()->getAttributes())->toBe($authorState)
        ->and(User::query()->where('email', NoticeTemplateBridgeDemoSeeder::AUTHOR_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', NoticeTemplateBridgeDemoSeeder::ASSESSMENT_EMAIL)->count())->toBe(1);
});

it('gives the dedicated assessment reviewer Results metadata only with no private props or authoring authority', function (): void {
    $this->withoutVite();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $reader = User::query()->where('email', NoticeTemplateBridgeDemoSeeder::ASSESSMENT_EMAIL)->firstOrFail();
    $scope = app(CommunicationTemplateScope::class);
    expect($scope->canAccessMetadata($reader, 'results'))->toBeTrue()
        ->and($scope->canAccessFullSource($reader, 'results'))->toBeFalse();
    $family = '/setup/notifications/results/templates/released-online';
    $this->actingAs($reader)->get('/setup/notifications/results')->assertOk()
        ->assertInertia(fn ($page) => $page->has('notices', count(CommunicationTemplateRegistry::resultsSlots()))
            ->where('capabilities.can_manage', false)
            ->missing('notices.0.sample_subject')->missing('notices.0.sample_body')
            ->missing('notices.0.tokens')->missing('notices.0.editor_url')->missing('notices.0.endpoints'));
    $this->get($family)->assertOk()
        ->assertInertia(fn ($page) => $page->missing('draft.state')->missing('sampleContext')
            ->missing('placeholderGroups')->missing('family.email_blueprint')->missing('family.subject'));
    foreach (['/edit', '/editor-state'] as $endpoint) {
        $this->get($family.$endpoint)->assertForbidden();
    }
    foreach (['/drafts', '/preview', '/test-mail', '/publish'] as $endpoint) {
        $this->postJson($family.$endpoint, [])->assertForbidden();
    }
    foreach (['/import', '/import-review'] as $endpoint) {
        $this->postJson($family.$endpoint, [])->assertNotFound();
    }
});

it('provisions every canonical family only in the demo institution and preserves all defaults and reminder work on rerun', function (): void {
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    foreach (['admissions', 'examination', 'results'] as $domain) {
        $expected = array_column(CommunicationTemplateRegistry::slotsFor($domain), 'slug');
        sort($expected);
        expect(CommunicationTemplate::query()->where('institution_id', $institution->id)->where('domain', $domain)
            ->orderBy('slug')->pluck('slug')->all())->toBe($expected);
    }
    $families = CommunicationTemplate::query()->where('institution_id', $institution->id)->orderBy('id')->get();
    $familyIds = $families->pluck('id')->all();
    $familyStates = $families->map(fn ($family) => $family->getAttributes())->all();
    $versions = CommunicationTemplateVersion::query()->whereIn('communication_template_id', $familyIds)
        ->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all();
    $reminder = $families->first(fn ($family) => $family->domain === 'examination' && $family->slug === 'reminder');
    $draft = $reminder->draft()->firstOrFail()->getAttributes();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    expect(CommunicationTemplate::query()->where('institution_id', $institution->id)->orderBy('id')->get()
        ->map(fn ($family) => $family->getAttributes())->all())->toBe($familyStates)
        ->and(CommunicationTemplateVersion::query()->whereIn('communication_template_id', $familyIds)
            ->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all())->toBe($versions)
        ->and($reminder->draft()->firstOrFail()->getAttributes())->toBe($draft)
        ->and(CommunicationTemplate::query()->where('institution_id', '!=', $institution->id)->count())->toBe(0);
});

it('refuses to rearm an idle generation that already owns new schema reminder evidence', function (): void {
    $this->freezeSecond();
    (new NoticeTemplateBridgeDemoSeeder)->run();
    $family = publishNoticeBridgePreparationTestVersion();
    $session = ExamSession::query()->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->firstOrFail();
    $assignment = $session->assignments()->with('admissionAttempt.identity')->firstOrFail();
    $intent = NotificationIntent::v1('exam.reminder.v1', 'exam.reminder', [
        'session_id' => $session->id, 'assignment_id' => $assignment->id, 'window' => '24h',
        'subject' => 'Existing reminder evidence', 'body' => 'Preserve the current fixture.',
        'action_label' => 'View schedule', 'action_url' => route('applicant.schedule'),
    ]);
    $delivery = DB::transaction(fn () => app(NotificationIntentDispatcher::class)
        ->dispatch($intent, hash('sha256', 'existing-notice-bridge-evidence'), [$assignment->admissionAttempt->identity], ['in_app']))->firstOrFail();
    expect($delivery->payload['snapshot_schema_version'])->toBe('notice-delivery-2')
        ->and($delivery->payload)->not->toHaveKey('session_id')
        ->and($delivery->payload['event_resource']['id'])->toBe($session->id);
    $before = $session->getAttributes();
    $snapshot = $delivery->payload;
    $this->artisan('demo:prepare-notice-bridge-reminder', ['--template-version' => $family->active_version])->assertFailed();
    expect($session->fresh()->getAttributes())->toBe($before)
        ->and($delivery->fresh()->payload)->toBe($snapshot);
    Mail::assertNothingSent();
});
