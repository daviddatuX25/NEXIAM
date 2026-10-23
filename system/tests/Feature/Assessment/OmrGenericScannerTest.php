<?php

use App\Assessment\Actions\Omr\AdoptOmrPendingCapture;
use App\Assessment\Actions\Omr\Canonical\ResolveOmrBlankInstanceManually;
use App\Assessment\Actions\Omr\CaptureGenericOmrImage;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function genericScannerPending(User $actor, array $overrides = []): OmrPendingCapture
{
    $scope = $actor->actorScope();
    $cycle = AcademicCycle::query()
        ->where('institution_id', $scope->institutionId)
        ->where('code', $scope->activeCycleCode)
        ->firstOrFail();

    return OmrPendingCapture::query()->create(array_merge([
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
        'capture_mode' => 'auto',
        'page_no' => 1,
        'page_count' => 1,
        'qr_state' => 'undecodable',
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'asset_path' => 'omr/pending/test/'.Str::uuid().'/captured.bin',
        'asset_hmac' => hash('sha256', 'scanner-test-asset'),
        'asset_format_version' => 'v1',
        'asset_state' => OmrPendingCapture::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => 18,
        'input_hash' => hash('sha256', 'scanner-test-input'),
        'uploaded_by_user_id' => $actor->id,
        'version' => 0,
        'expires_at' => now()->addHour(),
        'resolution_json' => ['resolution_code' => 'unreadable', 'candidates' => []],
        'analysis_code' => 'unreadable',
    ], $overrides));
}

test('canonical generic scanner renders with its generated capture endpoint', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $captureUrl = route('scoring.omr.scanner.capture');
    $this->actingAs($fixture['guidance'])
        ->get(route('scoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Assessment/Grading')
            ->where('scanner_url', route('scoring.omr.scanner')));

    $this->actingAs($fixture['guidance'])
        ->get('/scoring/omr')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Assessment/Omr/Scanner')
            ->where('pending_capture', null)
            ->where('capture_url', $captureUrl));

    $this->actingAs($fixture['guidance'])
        ->postJson($captureUrl, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image', 'client_capture_id', 'idempotency_key']);
});

test('scoring hub omits scanner affordance and scanner entry denies actors without OMR capability', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $actor->syncRoles([]);
    $actor->syncPermissions([CapabilityCatalog::GUIDANCE_SCORING_MANUAL]);

    $this->actingAs($actor)
        ->get(route('scoring.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Assessment/Grading')
            ->where('scanner_url', null));

    $this->actingAs($actor)
        ->get(route('scoring.omr.scanner'))
        ->assertForbidden();
});

test('pending recovery commands require a version and idempotency key', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $pending = genericScannerPending($fixture['guidance']);

    $this->actingAs($fixture['guidance'])
        ->postJson(route('scoring.omr.scanner.capture.retry', $pending), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expected_version', 'idempotency_key']);
});

test('pending capture owner and original scope are required for continuation', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $pending = genericScannerPending($fixture['guidance']);
    $other = User::factory()->create();
    $other->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $other->staffProfile()->create([
        'institution_id' => $fixture['guidance']->staffProfile->institution_id,
        'office' => $fixture['guidance']->staffProfile->office,
        'program_codes' => $fixture['guidance']->staffProfile->program_codes,
        'active_cycle_code' => $fixture['guidance']->staffProfile->active_cycle_code,
        'status' => 'active',
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $other->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
    ]);

    $this->actingAs($other)
        ->get(route('scoring.omr.scanner', ['pending_capture' => $pending->id]))
        ->assertForbidden();
});

test('a present invalid QR cannot be bypassed with a display code and writes no score', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $pending = genericScannerPending($fixture['guidance'], [
        'qr_state' => 'present_invalid',
        'analysis_code' => 'qr_invalid',
        'resolution_json' => ['resolution_code' => 'qr_invalid', 'candidates' => []],
    ]);
    $before = ScoreRecord::query()->count();

    $this->actingAs($fixture['guidance'])
        ->post(route('scoring.omr.scanner.capture.resolve-display-code', $pending), [
            'display_code' => 'DISPLAY-TEST',
            'manual_confirmed' => true,
            'expected_version' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('scanner');

    expect(ScoreRecord::query()->count())->toBe($before)
        ->and($pending->fresh()->status)->toBe(OmrPendingCapture::STATUS_NEEDS_RECOVERY);
});

test('Monitoring continuation rechecks the stored Applicant Number and keeps scoring uncommitted', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $attempt = $fixture['attempts'][0];
    app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'monitoring-recheck-'.Str::uuid(),
    );
    $pending = genericScannerPending($fixture['guidance'], [
        'status' => OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
        'qr_state' => 'verified',
        'detected_applicant_number' => $attempt->reference_code,
        'detected_applicant_confidence' => 0.99,
        'analysis_code' => null,
        'resolution_json' => [
            'resolution_code' => 'submission_missing',
            'candidates' => [],
        ],
    ]);

    $this->actingAs($fixture['guidance'])
        ->get(route('scoring.omr.scanner', ['pending_capture' => $pending->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('pending_capture.status', OmrPendingCapture::STATUS_READY)
            ->where('pending_capture.version', 1));

    expect($pending->fresh()->status)->toBe(OmrPendingCapture::STATUS_READY)
        ->and(ScoreRecord::query()->where('grading_session_id', $fixture['grading']->id)->count())->toBe(0);
});

test('stale adoption failure cannot resurrect a terminal pending capture', function (): void {
    $fixture = buildOmrGradingFixture($this);

    foreach ([
        OmrPendingCapture::STATUS_ADOPTED,
        OmrPendingCapture::STATUS_QUARANTINED,
        OmrPendingCapture::STATUS_FAILED,
    ] as $terminalStatus) {
        $pending = genericScannerPending($fixture['guidance'], [
            'status' => $terminalStatus,
            'version' => 1,
        ]);

        $this->actingAs($fixture['guidance'])
            ->post(route('scoring.omr.scanner.capture.adopt', $pending), [
                'expected_version' => 0,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('scanner');

        expect($pending->fresh()->status)->toBe($terminalStatus)
            ->and($pending->fresh()->version)->toBe(1);
    }
});

test('blank-instance adoption rejects institution and cycle scope mismatches', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $version = $fixture['session']->currentMaterialSnapshot()
        ->with('templateVersion')
        ->firstOrFail()
        ->templateVersion;
    $profile = $actor->staffProfile;
    $institutionId = (int) $profile->institution_id;
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $institutionId,
        'code' => 'SYN-OTHER-CYCLE-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Local Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $foreignInstitution = Institution::query()->create([
        'code' => 'SYN-OTHER-INST-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'SYN-FOREIGN-CYCLE-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Foreign Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $bytes = physicalChallengePng();
    $assetStore = app(SensitiveAssetStore::class);
    $assetPath = $assetStore->store($bytes, 'omr/pending/test/'.Str::uuid().'/captured.bin');
    $adoption = app(AdoptOmrPendingCapture::class);

    foreach ([$otherCycle, $foreignCycle] as $outOfScopeCycle) {
        $instance = app(OmrBlankInstanceService::class)->createPrintedSet(
            $version,
            $actor,
            1,
            [hash('sha256', 'scope-mismatch-'.$outOfScopeCycle->id)],
            null,
            null,
            null,
            null,
            OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
            1,
            (int) $outOfScopeCycle->institution_id,
            (int) $outOfScopeCycle->id,
        );
        $pending = genericScannerPending($actor, [
            'status' => OmrPendingCapture::STATUS_READY,
            'template_version_id' => $version->id,
            'blank_instance_id' => $instance->id,
            'blank_instance_page_id' => $instance->pages->firstOrFail()->id,
            'grading_session_id' => $fixture['grading']->id,
            'detected_applicant_number' => $fixture['attempts'][0]->reference_code,
            'asset_path' => $assetPath,
            'asset_hmac' => $assetStore->hmac($bytes),
            'original_bytes' => strlen($bytes),
            'input_hash' => hash('sha256', $bytes),
        ]);

        expect(fn () => $adoption->handle($pending, $actor, 0, (string) Str::uuid()))
            ->toThrow(RuntimeException::class, 'qr_scope_mismatch');
        expect($pending->fresh()->status)->toBe(OmrPendingCapture::STATUS_RESOLUTION_REQUIRED);
    }

});

test('template-less QR retry preserves a recoverable pending capture', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $bytes = physicalChallengePng();
    $assetStore = app(SensitiveAssetStore::class);
    $assetPath = $assetStore->store($bytes, 'omr/pending/test/'.Str::uuid().'/captured.bin');
    config(['omr.fake.qr_payload' => 'qr_reader_unavailable']);

    try {
        $pending = genericScannerPending($actor, [
            'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
            'qr_state' => 'undecodable',
            'analysis_code' => 'qr_reader_unavailable',
            'resolution_json' => [
                'resolution_code' => 'qr_reader_unavailable',
                'candidates' => [],
            ],
            'template_version_id' => null,
            'blank_instance_id' => null,
            'blank_instance_page_id' => null,
            'asset_path' => $assetPath,
            'asset_hmac' => $assetStore->hmac($bytes),
            'original_bytes' => strlen($bytes),
            'input_hash' => hash('sha256', $bytes),
        ]);
        $result = app(CaptureGenericOmrImage::class)->retry(
            $pending,
            $actor,
            0,
            (string) Str::uuid(),
        );
    } finally {
        config(['omr.fake.qr_payload' => null]);
    }

    expect($result->status)->toBe(OmrPendingCapture::STATUS_NEEDS_RECOVERY)
        ->and($result->isRecoverable())->toBeTrue()
        ->and($result->analysis_code)->toBe('qr_reader_unavailable')
        ->and($result->template_version_id)->toBeNull()
        ->and($result->version)->toBe(2);
});

test('blank display-code scope remains immutable after issuer profile changes', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $originalInstitutionId = (int) $actor->staffProfile->institution_id;
    $originalCycleCode = (string) $actor->staffProfile->active_cycle_code;
    $originalCycleId = (int) AcademicCycle::query()
        ->where('institution_id', $originalInstitutionId)
        ->where('code', $originalCycleCode)
        ->value('id');
    $version = $fixture['session']->currentMaterialSnapshot()
        ->with('templateVersion')
        ->firstOrFail()
        ->templateVersion;
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet(
        $version,
        $actor,
        1,
        [hash('sha256', 'immutable-scope-page')],
    );

    $otherInstitution = Institution::query()->create([
        'code' => 'SYN-OTHER-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Institution',
    ]);
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'SYN-OTHER-CYCLE-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $actor->staffProfile()->update([
        'institution_id' => $otherInstitution->id,
        'active_cycle_code' => $otherCycle->code,
    ]);

    $action = app(ResolveOmrBlankInstanceManually::class);
    expect(fn () => $action->handle(
        $actor,
        'absent',
        $instance->display_code,
        true,
        (int) $otherInstitution->id,
        (string) $otherCycle->code,
    ))->toThrow(OmrBlankInstanceException::class, 'instance_not_found');

    $resolved = $action->handle(
        $actor,
        'absent',
        $instance->display_code,
        true,
        $originalInstitutionId,
        $originalCycleCode,
    );
    expect($instance->institution_id)->toBe($originalInstitutionId)
        ->and($instance->academic_cycle_id)->toBe($originalCycleId)
        ->and($resolved['instance']->id)->toBe($instance->id);
});
