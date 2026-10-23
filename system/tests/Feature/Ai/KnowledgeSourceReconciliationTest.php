<?php

use App\Ai\Knowledge\Actions\ReviewKnowledgeSourceDisposition;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceReconciliation;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
});

test('1. authorized registrar can retain an internal registrar procedure', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'registrar-internal-procedure-'.Str::random(6),
        'title' => 'Student Record Archival Procedure',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Internal procedures for archival of legacy applicant records.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Student Record Archival Procedure v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);
    $reconciliation = $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Retain internal procedure for staff records operations.',
    ]);

    expect($reconciliation)->toBeInstanceOf(KnowledgeSourceReconciliation::class)
        ->and($reconciliation->knowledge_source_id)->toBe($source->id)
        ->and($reconciliation->knowledge_source_version_id)->toBe($version->id)
        ->and($reconciliation->disposition)->toBe(KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL)
        ->and($reconciliation->checksum_at_review)->toBe($checksum)
        ->and($reconciliation->reviewed_by)->toBe($registrar->id)
        ->and($reconciliation->target_type)->toBeNull()
        ->and($reconciliation->target_id)->toBeNull();
});

test('2. authorized registrar can place an unreviewed synthetic FAQ on hold', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'synthetic-staff-readiness-faq-'.Str::random(6),
        'title' => 'Synthetic Staff Readiness FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Unreviewed synthetic staff training FAQ awaiting review.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED,
        'title' => 'Synthetic Staff Readiness FAQ v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);
    $reconciliation = $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_HOLD,
        'reason' => 'Synthetic FAQ placed on hold pending human owner disposition review.',
    ]);

    expect($reconciliation->disposition)->toBe(KnowledgeSourceReconciliation::DISPOSITION_HOLD)
        ->and($reconciliation->reason)->toBe('Synthetic FAQ placed on hold pending human owner disposition review.');

    expect(KnowledgeSourceReconciliation::query()->where('knowledge_source_version_id', $version->id)->count())->toBe(1);
});

test('3. idempotent replay: re-submitting identical payload returns same record without duplicates', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'idempotent-replay-test-'.Str::random(6),
        'title' => 'Idempotent Replay Source',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Consistent body content for replay testing.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Idempotent Replay v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $payload = [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Retain verified internal operational procedure.',
    ];

    $action = app(ReviewKnowledgeSourceDisposition::class);
    $firstRun = $action->handle($registrar, $payload);
    $secondRun = $action->handle($registrar, $payload);

    expect($secondRun->id)->toBe($firstRun->id)
        ->and(KnowledgeSourceReconciliation::query()->where('knowledge_source_version_id', $version->id)->count())->toBe(1);
});

test('4. stale checksum rejected: mismatched expected_checksum throws ValidationException without mutating', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'stale-checksum-test-'.Str::random(6),
        'title' => 'Stale Checksum Source',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $actualChecksum = hash('sha256', 'Current actual version content.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Stale Checksum v1',
        'body_text' => 'Current actual version content.',
        'content_checksum' => $actualChecksum,
        'created_by' => (string) $registrar->id,
    ]);

    $staleChecksum = hash('sha256', 'Prior outdated version content.');
    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $staleChecksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Attempted review with stale checksum.',
    ]))->toThrow(ValidationException::class);

    expect(KnowledgeSourceReconciliation::query()->where('knowledge_source_version_id', $version->id)->count())->toBe(0);
});

test('5. revoked version cannot be reviewed', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'revoked-version-test-'.Str::random(6),
        'title' => 'Revoked Policy Source',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Revoked policy body.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_REVOKED,
        'title' => 'Revoked Policy v1',
        'body_text' => 'Revoked policy body.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
        'revoked_at' => now(),
        'revoke_reason' => 'Superseded by new statutory guidance',
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Attempting review of revoked version.',
    ]))->toThrow(ValidationException::class);
});

test('6. cross-office denial: Guidance counselor cannot review a registrar office-internal source', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'registrar-office-internal-'.Str::random(6),
        'title' => 'Registrar Internal Exam Attendance Protocol',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Strict internal registrar attendance rules.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Registrar Attendance Protocol v1',
        'body_text' => 'Strict internal registrar attendance rules.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($guidance, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Guidance counselor attempting to review registrar procedure.',
    ]))->toThrow(AuthorizationException::class);
});

test('7. unauthorized actor (applicant or clerk without knowledge_governance) is forbidden', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $applicantUser = User::factory()->create();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'governance-auth-test-'.Str::random(6),
        'title' => 'Admissions Benchmark Protocol',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Benchmark policy content.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Benchmark Protocol v1',
        'body_text' => 'Benchmark policy content.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);
    $payload = [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Unauthorized review attempt.',
    ];

    expect(fn () => $action->handle($clerk, $payload))->toThrow(AuthorizationException::class);
    expect(fn () => $action->handle($applicantUser, $payload))->toThrow(AuthorizationException::class);
});

test('8. held version is excluded from cross-office visibility', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $access = app(KnowledgeSourceAccess::class);

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'held-cross-office-faq-'.Str::random(6),
        'title' => 'Cross Office Training FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Cross office procedure body.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Cross Office Training FAQ v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    // Before hold: Guidance counselor (as office head) can view cross-office staff source
    expect($access->canViewSource($guidance, $source, $version))->toBeTrue()
        ->and($access->canViewSource($registrar, $source, $version))->toBeTrue();

    // Place version on hold
    $action = app(ReviewKnowledgeSourceDisposition::class);
    $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_HOLD,
        'reason' => 'Held pending owner review of cross office implications.',
    ]);

    // After hold:
    // Registrar (owning office) can still view for draft/history inspection
    expect($access->canViewSource($registrar, $source, $version))->toBeTrue();

    // Guidance counselor (cross office) CANNOT view
    expect($access->canViewSource($guidance, $source, $version))->toBeFalse()
        ->and($access->hasPublishedCrossOfficeVisibility($source, $version))->toBeFalse()
        ->and($access->hasPublishedInstitutionalVisibility($source, $version))->toBeFalse();
});

test('reference_public disposition verifies target public policy exists and is active', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'ref-public-test-'.Str::random(6),
        'title' => 'Public Policy Reference Candidate',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Reconciled public policy text.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Public Policy Reference Candidate v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    // 1. Target does not exist -> ValidationException
    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC,
        'target_type' => PublicPolicy::class,
        'target_id' => 999999,
        'reason' => 'Referencing non-existent public policy.',
    ]))->toThrow(ValidationException::class);

    // 2. Target exists but is draft (not published) -> ValidationException
    $draftPolicy = PublicPolicy::query()->create([
        'institution_id' => $institution->id,
        'publisher_key' => 'office:registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Draft Admissions Policy',
        'content' => 'Draft policy content.',
        'revision' => 1,
        'status' => 'draft',
        'created_by' => $registrar->id,
    ]);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC,
        'target_type' => PublicPolicy::class,
        'target_id' => $draftPolicy->id,
        'reason' => 'Referencing draft public policy.',
    ]))->toThrow(ValidationException::class);

    // 3. Target exists and is published -> Success!
    $publishedPolicy = PublicPolicy::query()->create([
        'institution_id' => $institution->id,
        'publisher_key' => 'office:registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Published Admissions Policy',
        'content' => 'Active policy content.',
        'revision' => 1,
        'status' => 'published',
        'created_by' => $registrar->id,
        'published_by' => $registrar->id,
        'published_at' => now(),
    ]);

    $reconciliation = $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC,
        'target_type' => PublicPolicy::class,
        'target_id' => $publishedPolicy->id,
        'target_version' => 1,
        'reason' => 'Reconciled to published public policy.',
    ]);

    expect($reconciliation->disposition)->toBe(KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC)
        ->and($reconciliation->target_type)->toBe(PublicPolicy::class)
        ->and((int) $reconciliation->target_id)->toBe($publishedPolicy->id)
        ->and($reconciliation->target_version)->toBe(1);
});

test('cross-office denial: Registrar cannot review a guidance office-internal source', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'guidance-office-internal-'.Str::random(6),
        'title' => 'Guidance Counseling Internal Protocol',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'guidance',
        'owner_user_id' => (string) $guidance->id,
    ]);

    $checksum = hash('sha256', 'Strict internal guidance counseling rules.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Guidance Protocol v1',
        'body_text' => 'Strict internal guidance counseling rules.',
        'content_checksum' => $checksum,
        'created_by' => (string) $guidance->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Registrar attempting to review guidance procedure.',
    ]))->toThrow(AuthorizationException::class, "Actor from office 'registrar' cannot review sources owned by 'guidance'.");
});

test('mismatched source and version throws ValidationException and does not mutate', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $sourceA = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'source-a-'.Str::random(6),
        'title' => 'Source A',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $sourceB = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'source-b-'.Str::random(6),
        'title' => 'Source B',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Version under source B.');
    $versionB = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $sourceB->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Version B v1',
        'body_text' => 'Version under source B.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $sourceA->id,
        'knowledge_source_version_id' => $versionB->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Mismatched version association test.',
    ]))->toThrow(ValidationException::class);
});

test('changed parameters on already-reconciled version throws ValidationException and does not mutate', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'immutable-recon-test-'.Str::random(6),
        'title' => 'Immutable Reconciliation Test',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Reconciled immutable content.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Immutable v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    $reconciliation = $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Initial internal retention reason.',
    ]);

    expect($reconciliation->disposition)->toBe(KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_HOLD,
        'reason' => 'Changed disposition to hold.',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'A completely different reason.',
    ]))->toThrow(ValidationException::class);

    $fresh = $reconciliation->fresh();
    expect($fresh->disposition)->toBe(KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL)
        ->and($fresh->reason)->toBe('Initial internal retention reason.')
        ->and(KnowledgeSourceReconciliation::query()->where('knowledge_source_version_id', $version->id)->count())->toBe(1);
});

test('target type injection rejected when target_type is not PublicPolicy', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'target-whitelist-test-'.Str::random(6),
        'title' => 'Target Whitelist Test',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Target whitelist test body.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Whitelist Test v1',
        'body_text' => 'Target whitelist test body.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC,
        'target_type' => User::class,
        'target_id' => $registrar->id,
        'reason' => 'Attempting target type injection with User model.',
    ]))->toThrow(ValidationException::class);

    expect(KnowledgeSourceReconciliation::query()->where('knowledge_source_version_id', $version->id)->count())->toBe(0);
});

test('isHeld inspects latest reconciliation rather than any historical hold', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $access = app(KnowledgeSourceAccess::class);

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'latest-recon-test-'.Str::random(6),
        'title' => 'Latest Recon Status Check',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $body = 'Reconciliation history check body.';
    $checksum = hash('sha256', $body);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Latest Recon v1',
        'body_text' => $body,
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $hold = new KnowledgeSourceReconciliation([
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_HOLD,
        'checksum_at_review' => $checksum,
        'reviewed_by' => $registrar->id,
        'reviewed_at' => now()->subDay(),
        'reason' => 'Initially placed on hold yesterday.',
    ]);
    $hold->created_at = now()->subDay();
    $hold->save();

    expect($access->canViewSource($guidance, $source, $version))->toBeFalse();

    $release = new KnowledgeSourceReconciliation([
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'checksum_at_review' => $checksum,
        'reviewed_by' => $registrar->id,
        'reviewed_at' => now(),
        'reason' => 'Released from hold today.',
    ]);
    $release->created_at = now();
    $release->save();

    expect($access->canViewSource($guidance, $source, $version))->toBeTrue();
});

test('cross-institution tenant check: actor from different institution cannot review source', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $otherInstitution = Institution::query()->create([
        'code' => 'OTHER-INST-'.Str::random(4),
        'name' => 'Other Institution',
    ]);

    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'cross-inst-test-'.Str::random(6),
        'title' => 'Other Inst Source',
        'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
        'institution_id' => $otherInstitution->id,
        'owning_office' => 'registrar',
        'owner_user_id' => (string) $registrar->id,
    ]);

    $checksum = hash('sha256', 'Other inst content.');
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Other Inst v1',
        'body_text' => 'Other inst content.',
        'content_checksum' => $checksum,
        'created_by' => (string) $registrar->id,
    ]);

    $action = app(ReviewKnowledgeSourceDisposition::class);

    expect(fn () => $action->handle($registrar, [
        'knowledge_source_id' => $source->id,
        'knowledge_source_version_id' => $version->id,
        'expected_checksum' => $checksum,
        'disposition' => KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
        'reason' => 'Attempting cross-institution review.',
    ]))->toThrow(AuthorizationException::class, 'Cross-institution knowledge access denied.');
});
