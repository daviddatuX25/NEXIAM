<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();

    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
});

test('registrar can bulk accept pending attempts and increment their versions', function (): void {
    $attemptOne = submitSyntheticApplicant('bulk.accept.one@example.test', 'BulkOne')['attempt'];
    $attemptTwo = submitSyntheticApplicant('bulk.accept.two@example.test', 'BulkTwo')['attempt'];

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [
                ['id' => $attemptOne->id, 'version' => $attemptOne->version],
                ['id' => $attemptTwo->id, 'version' => $attemptTwo->version],
            ],
        ])
        ->assertRedirect();

    expect($attemptOne->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($attemptOne->fresh()->version)->toBe($attemptOne->version + 1)
        ->and($attemptTwo->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($attemptTwo->fresh()->version)->toBe($attemptTwo->version + 1);
});

test('bulk accept skips attempts submitted with a stale version', function (): void {
    $attempt = submitSyntheticApplicant('bulk.accept.stale@example.test', 'BulkStale')['attempt'];

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [
                ['id' => $attempt->id, 'version' => $attempt->version + 1],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($attempt->fresh()->version)->toBe($attempt->version);
});

test('registrar can bulk request correction with a persisted reason', function (): void {
    $attempt = submitSyntheticApplicant('bulk.correction@example.test', 'BulkCorrection')['attempt'];
    $reason = 'Please provide a legible Form 138 scan.';

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-request-correction'), [
            'attempts' => [
                ['id' => $attempt->id, 'version' => $attempt->version],
            ],
            'reason' => $reason,
        ])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and($attempt->fresh()->correction_notes)->toBe($reason)
        ->and($attempt->fresh()->version)->toBe($attempt->version + 1);

    $audit = AuditEvent::query()
        ->where('event_type', 'admission.correction_requested')
        ->where('payload->attempt_id', $attempt->id)
        ->firstOrFail();

    expect($audit->payload['reason'])->toBe($reason);
});

test('single admission transitions hide attempts from another institution', function (): void {
    $attempt = submitSyntheticApplicant('foreign.single@example.test', 'ForeignSingle')['attempt'];
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-SINGLE',
        'name' => 'Foreign Single Institution',
    ]);
    $attempt->forceFill(['institution_id' => $foreignInstitution->id])->save();

    $this->actingAs($this->registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => $attempt->version])
        ->assertNotFound();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING);
});

test('bulk admission transitions reject foreign institution attempts before writing any row', function (): void {
    $inScopeAttempt = submitSyntheticApplicant('bulk.scope.valid@example.test', 'BulkScopeValid')['attempt'];
    $foreignAttempt = submitSyntheticApplicant('bulk.scope.foreign@example.test', 'BulkScopeForeign')['attempt'];
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-BULK',
        'name' => 'Foreign Bulk Institution',
    ]);
    $foreignAttempt->forceFill(['institution_id' => $foreignInstitution->id])->save();

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [
                ['id' => $inScopeAttempt->id, 'version' => $inScopeAttempt->version],
                ['id' => $foreignAttempt->id, 'version' => $foreignAttempt->version],
            ],
        ])
        ->assertNotFound();

    expect($inScopeAttempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($foreignAttempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING);
});

test('bulk admission request rejects duplicate ids and versions below one', function (): void {
    $attempt = submitSyntheticApplicant('bulk.validation@example.test', 'BulkValidation')['attempt'];

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [
                ['id' => $attempt->id, 'version' => $attempt->version],
                ['id' => $attempt->id, 'version' => $attempt->version],
            ],
        ])
        ->assertSessionHasErrors('attempts.1.id');

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [['id' => $attempt->id, 'version' => 0]],
        ])
        ->assertSessionHasErrors('attempts.0.version');
});

test('bulk correction reports conflicts per item while processing valid attempts', function (): void {
    $pendingAttempt = submitSyntheticApplicant('bulk.correction.valid@example.test', 'BulkCorrectionValid')['attempt'];
    $acceptedAttempt = submitSyntheticApplicant('bulk.correction.conflict@example.test', 'BulkCorrectionConflict')['attempt'];
    $acceptedAttempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();

    $this->actingAs($this->registrar)
        ->post(route('admissions.bulk-request-correction'), [
            'attempts' => [
                ['id' => $pendingAttempt->id, 'version' => $pendingAttempt->version],
                ['id' => $acceptedAttempt->id, 'version' => $acceptedAttempt->version],
            ],
            'reason' => 'Please provide a legible Form 138 scan.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', '1 application(s) sent for correction. 1 skipped.')
        ->assertSessionHasErrors();

    expect($pendingAttempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and($acceptedAttempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
});

test('staff without registrar capabilities cannot invoke bulk admission actions', function (): void {
    $attempt = submitSyntheticApplicant('bulk.unauthorized@example.test', 'BulkUnauthorized')['attempt'];

    $this->actingAs($this->guidance)
        ->post(route('admissions.bulk-accept'), [
            'attempts' => [['id' => $attempt->id, 'version' => $attempt->version]],
        ])
        ->assertForbidden();

    $this->actingAs($this->guidance)
        ->post(route('admissions.bulk-request-correction'), [
            'attempts' => [['id' => $attempt->id, 'version' => $attempt->version]],
            'reason' => 'Unauthorized attempt',
        ])
        ->assertForbidden();
});
