<?php

use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\Canonical\SupersedeOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\Canonical\VoidOmrBlankInstanceSet;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Printing\PrintJob;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** @return array{actor: User, institution: Institution, cycle: AcademicCycle} */
function omrPrintScopeStaff(string $suffix): array
{
    $identifier = strtoupper(Str::random(10));
    $institution = Institution::query()->create([
        'code' => 'PRINT-'.$identifier,
        'name' => 'Print scope '.$suffix,
    ]);
    $cycleCode = 'PRINT-CYCLE-'.$identifier;
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => $cycleCode,
        'name' => 'Print cycle '.$suffix,
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
    ]);
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'active_cycle_code' => $cycleCode,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    return ['actor' => $actor, 'institution' => $institution, 'cycle' => $cycle];
}

function omrPrintScopeInstance(
    OmrAnswerSheetTemplateVersion $version,
    array $staff,
    int $pageSetNo,
): OmrBlankInstance {
    $instanceCode = 'NI-'.strtoupper((string) Str::ulid());

    return OmrBlankInstance::query()->create([
        'template_version_id' => $version->id,
        'institution_id' => $staff['institution']->id,
        'academic_cycle_id' => $staff['cycle']->id,
        'instance_code' => $instanceCode,
        'display_code' => 'NI-'.substr($instanceCode, 3, 11),
        'page_set_no' => $pageSetNo,
        'page_count' => 1,
        'qr_manifest_hash' => hash('sha256', $instanceCode),
        'signing_key_id' => 'scope-test-v1',
        'created_by_user_id' => $staff['actor']->id,
    ]);
}

function omrPrintScopeJob(
    OmrAnswerSheetTemplateVersion $version,
    array $staff,
    string $filePath,
    string $bytes,
): PrintJob {
    return PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
        'scope_type' => 'template_version',
        'scope_id' => $version->id,
        'status' => PrintJob::STATUS_READY,
        'requested_by_user_id' => $staff['actor']->id,
        'file_path' => $filePath,
        'file_checksum' => hash('sha256', $bytes),
        'pinned_input_json' => [
            'institution_id' => $staff['institution']->id,
            'academic_cycle_id' => $staff['cycle']->id,
        ],
        'output_manifest' => ['copies' => []],
    ]);
}

test('OMR issuance history is limited to the requesting guidance institution and cycle', function (): void {
    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
    ]);
    $local = omrPrintScopeStaff('local');
    $foreign = omrPrintScopeStaff('foreign');
    $localInstance = omrPrintScopeInstance($version, $local, 1);
    $foreignInstance = omrPrintScopeInstance($version, $foreign, 2);
    $localIssuance = OmrQrDisabledIssuance::query()->create([
        'template_version_id' => $version->id,
        'institution_id' => $local['institution']->id,
        'academic_cycle_id' => $local['cycle']->id,
        'issuance_code' => 'QDI-'.strtoupper((string) Str::uuid()),
        'page_count' => 1,
        'copy_policy' => OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
        'intended_copy_count' => 1,
        'created_by_user_id' => $local['actor']->id,
    ]);
    $foreignIssuance = OmrQrDisabledIssuance::query()->create([
        'template_version_id' => $version->id,
        'institution_id' => $foreign['institution']->id,
        'academic_cycle_id' => $foreign['cycle']->id,
        'issuance_code' => 'QDI-'.strtoupper((string) Str::uuid()),
        'page_count' => 1,
        'copy_policy' => OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
        'intended_copy_count' => 1,
        'created_by_user_id' => $foreign['actor']->id,
    ]);

    $this->actingAs($local['actor'])
        ->getJson(route('setup.omr.templates.canonical.blank.instances', $version))
        ->assertOk()
        ->assertJsonCount(1, 'instances')
        ->assertJsonPath('instances.0.id', $localInstance->id)
        ->assertJsonCount(1, 'qr_disabled_issuances')
        ->assertJsonPath('qr_disabled_issuances.0.id', $localIssuance->id)
        ->assertDontSee($foreignInstance->display_code)
        ->assertDontSee($foreignIssuance->issuance_code);
});

test('foreign guidance staff cannot void or supersede another institution blank set', function (): void {
    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
    ]);
    $local = omrPrintScopeStaff('local-mutation');
    $foreign = omrPrintScopeStaff('foreign-mutation');
    $instance = omrPrintScopeInstance($version, $foreign, 1);
    $replacement = omrPrintScopeInstance($version, $foreign, 2);

    $this->actingAs($local['actor'])
        ->postJson(route('setup.omr.templates.canonical.blank.instances.void', [$version, $instance]), [
            'idempotency_key' => 'foreign-void',
        ])
        ->assertForbidden();

    expect($instance->fresh()->status)->toBe(OmrBlankInstance::STATUS_PRINTED)
        ->and(fn () => app(VoidOmrBlankInstanceSet::class)->handle($instance, $local['actor']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(SupersedeOmrBlankInstanceSet::class)->handle($instance, $replacement, $local['actor']))
        ->toThrow(AuthorizationException::class);
});

test('a signed blank print cannot issue without a complete authorized guidance scope', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
    ]);

    expect(fn () => app(PrintOmrBlankInstances::class)->handle($version, $actor, 1, 'unscoped-signed-print'))
        ->toThrow(AuthorizationException::class)
        ->and(PrintJob::query()->count())->toBe(0);
});

test('OMR print job status and ZIP download require the job institution and use ZIP metadata', function (): void {
    Storage::fake('local');
    $local = omrPrintScopeStaff('download-local');
    $foreign = omrPrintScopeStaff('download-foreign');
    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
    ]);
    $path = 'omr-canonical/print-jobs/'.$version->id.'/qr-disabled-issuance.zip';
    $bytes = "PK\x03\x04downloadable-zip";
    Storage::disk('local')->put($path, $bytes);
    $job = omrPrintScopeJob($version, $local, $path, $bytes);

    $this->actingAs($local['actor'])
        ->getJson(route('printing.jobs.show', $job))
        ->assertOk()
        ->assertJsonPath('id', $job->id)
        ->assertJsonPath('outputs', ['zip']);

    $this->actingAs($local['actor'])
        ->get(route('printing.jobs.download', $job))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('Content-Disposition', 'inline; filename="print-'.$job->id.'.zip"');

    $this->actingAs($foreign['actor'])
        ->getJson(route('printing.jobs.show', $job))
        ->assertForbidden();
    $this->actingAs($foreign['actor'])
        ->get(route('printing.jobs.download', $job))
        ->assertForbidden();
    $this->actingAs($local['actor'])
        ->get(route('printing.jobs.download', [$job, 'format' => 'pdf']))
        ->assertStatus(409);
});

test('blank-print replay reports the linked print job current status', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.canonical.storage.disk' => 'local']);
    $staff = omrPrintScopeStaff('replay-status');
    $version = physicalChallengeVersion($staff['actor']);
    $version->forceFill(['status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE])->save();
    $route = route('setup.omr.templates.canonical.blank.print', $version);
    $payload = ['quantity' => 1, 'idempotency_key' => 'blank-print-replay-status'];

    $queued = $this->actingAs($staff['actor'])
        ->postJson($route, $payload)
        ->assertStatus(202)
        ->assertJsonPath('status', PrintJob::STATUS_QUEUED);
    $job = PrintJob::query()->findOrFail((int) $queued->json('print_job_id'));

    $job->forceFill(['status' => PrintJob::STATUS_READY])->save();
    $this->actingAs($staff['actor'])
        ->postJson($route, $payload)
        ->assertOk()
        ->assertJsonPath('print_job_id', $job->id)
        ->assertJsonPath('status', PrintJob::STATUS_READY);

    $job->forceFill([
        'status' => PrintJob::STATUS_FAILED,
        'safe_error' => 'render_failed',
    ])->save();
    $this->actingAs($staff['actor'])
        ->postJson($route, $payload)
        ->assertStatus(409)
        ->assertJsonPath('print_job_id', $job->id)
        ->assertJsonPath('status', PrintJob::STATUS_FAILED)
        ->assertJsonPath('error', 'render_failed');

    expect(PrintJob::query()->count())->toBe(1);
});
