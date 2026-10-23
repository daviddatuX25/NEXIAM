<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     actor: User,
 *     batch: OmrCaptureBatch,
 *     capture_page: OmrCaptureSheetPage,
 *     instance: OmrBlankInstance,
 *     sheet: OmrCaptureSheet,
 *     exam_session_id: int
 * }
 */
function omrBlankInstanceRegistrationFixture(TestCase $test): array
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
    ]);
    $gradingFixture = buildOmrGradingFixture($test);
    $actor = $gradingFixture['guidance'];
    $snapshot = $gradingFixture['session']->currentMaterialSnapshot()
        ->with('templateVersion')
        ->firstOrFail();
    $version = $snapshot->templateVersion;
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $gradingFixture['grading'],
        $actor,
        'blank-instance-registration-'.Str::uuid(),
    );
    $pageCount = $version->pageCount();
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet(
        $version,
        $actor,
        $pageCount,
        array_fill(0, $pageCount, hash('sha256', 'registered-page')),
    );
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'admission_attempt_id' => $gradingFixture['attempts'][0]->id,
        'status' => OmrCaptureSheet::STATUS_RECEIVED,
        'expected_page_count' => $pageCount,
        'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
        'client_capture_id' => (string) Str::uuid(),
        'version' => 0,
        'asset_path' => 'omr-captures/test/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'capture-page-asset'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_PENDING_WRITE,
    ]);
    $capturePage = OmrCaptureSheetPage::query()->create([
        'sheet_id' => $sheet->id,
        'page_no' => 1,
        'status' => OmrCaptureSheetPage::STATUS_QUEUED,
    ]);

    return [
        'actor' => $actor,
        'batch' => $batch,
        'capture_page' => $capturePage,
        'instance' => $instance,
        'sheet' => $sheet,
        'exam_session_id' => (int) $gradingFixture['session']->id,
    ];
}

test('signed instance registration rejects a capture page with the wrong page number', function (): void {
    $fixture = omrBlankInstanceRegistrationFixture($this);
    $fixture['capture_page']->forceFill(['page_no' => 2])->save();

    expect(fn () => app(OmrBlankInstanceService::class)->registerCapture(
        $fixture['instance'],
        1,
        $fixture['capture_page'],
        $fixture['actor'],
        $fixture['exam_session_id'],
    ))->toThrow(OmrBlankInstanceException::class, 'capture_page_mismatch');

    expect($fixture['instance']->claims()->count())->toBe(0)
        ->and($fixture['instance']->fresh()->status)->toBe(OmrBlankInstance::STATUS_PRINTED);
});

test('signed instance registration rejects a capture batch pinned to another template version', function (): void {
    $fixture = omrBlankInstanceRegistrationFixture($this);
    $otherVersion = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
    ]);
    $fixture['batch']->forceFill(['template_version_id' => $otherVersion->id])->save();

    expect(fn () => app(OmrBlankInstanceService::class)->registerCapture(
        $fixture['instance'],
        1,
        $fixture['capture_page'],
        $fixture['actor'],
        $fixture['exam_session_id'],
    ))->toThrow(OmrBlankInstanceException::class, 'template_mismatch');

    expect($fixture['instance']->claims()->count())->toBe(0)
        ->and($fixture['instance']->fresh()->status)->toBe(OmrBlankInstance::STATUS_PRINTED);
});

test('signed instance registration rejects a capture applicant from another academic cycle', function (): void {
    $fixture = omrBlankInstanceRegistrationFixture($this);
    $originalAttempt = $fixture['sheet']->admissionAttempt()->firstOrFail();
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $originalAttempt->institution_id,
        'code' => 'OMR-REG-'.Str::upper(Str::random(8)),
        'name' => 'Registration integrity foreign cycle',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
    ]);
    $foreignAttempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $originalAttempt->applicant_identity_id,
        'institution_id' => $originalAttempt->institution_id,
        'academic_cycle_id' => $foreignCycle->id,
        'reference_code' => 'OMR-REG-'.Str::upper(Str::random(12)),
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
    ]);
    $fixture['sheet']->forceFill(['admission_attempt_id' => $foreignAttempt->id])->save();

    expect(fn () => app(OmrBlankInstanceService::class)->registerCapture(
        $fixture['instance'],
        1,
        $fixture['capture_page'],
        $fixture['actor'],
        $fixture['exam_session_id'],
    ))->toThrow(OmrBlankInstanceException::class, 'capture_scope_mismatch');

    expect($fixture['instance']->claims()->count())->toBe(0)
        ->and($fixture['instance']->fresh()->status)->toBe(OmrBlankInstance::STATUS_PRINTED);
});
