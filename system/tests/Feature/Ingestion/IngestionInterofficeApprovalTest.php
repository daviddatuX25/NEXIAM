<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\ScoreConversionRow;
use App\Models\Foundation\SpaceKind;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
function interofficeApprovalEnvironment(bool $delegated = false): array
{
    $institution = Institution::query()->create([
        'code' => 'SYN-INST-001',
        'name' => 'Synthetic Institution',
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'AY-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
        'allow_guidance_all_in_one_intake' => $delegated,
    ]);
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'BSCS',
        'name' => 'Bachelor of Science in Computer Science',
        'is_active' => true,
    ]);
    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-101',
        'name' => 'Main Testing Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 50,
        'is_active' => true,
    ]);

    foreach ([['MATH', 'Mathematics'], ['SCIENCE', 'Science']] as [$code, $name]) {
        $area = AptitudeArea::query()->create([
            'code' => $code,
            'name' => $name,
            'max_items' => 50,
            'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
            'is_active' => true,
        ]);
        ScoreConversionRow::query()->create([
            'aptitude_area_id' => $area->id,
            'min_score' => 0,
            'max_score' => 50,
            'output_value' => '100',
        ]);
    }

    return compact('institution', 'cycle', 'program', 'room');
}

function interofficeApprovalActor(array $capabilities): User
{
    $actor = User::factory()->create();
    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
        $actor->givePermissionTo($capability);
    }

    return $actor;
}

function stageGuidanceAllInOneBatch(object $test, AcademicCycle $cycle, User $guidance): ImportBatch
{
    $csv = implode("\n", [
        'first_name,last_name,birthdate,sex,course,applicant_number,email,strand,school,math,science',
        'Maria,Santos,2005-04-12,female,BSCS,REF-MARIA,maria@example.test,STEM,North High,45,42',
    ]);

    $tmpPath = tempnam(sys_get_temp_dir(), 'csv_').'.csv';
    file_put_contents($tmpPath, $csv);
    $uploadedFile = new UploadedFile($tmpPath, 'examinees.csv', 'text/csv', null, true);

    $response = $test->actingAs($guidance)
        ->from(route('imports.index'))
        ->post(route('imports.stage'), [
            'file' => $uploadedFile,
            'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
            'academic_cycle_id' => $cycle->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    $response->assertRedirect("/imports/{$batch->id}/review");

    return $batch;
}

test('guidance staging all in one without delegation enters pending approval', function (): void {
    $env = interofficeApprovalEnvironment();
    $guidance = interofficeApprovalActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);

    $batch = stageGuidanceAllInOneBatch($this, $env['cycle'], $guidance);
    $this->withoutVite();
    $this->actingAs($guidance)
        ->get(route('imports.review', $batch))
        ->assertOk();

    expect($batch->status)->toBe(ImportBatch::STATUS_PENDING_APPROVAL)
        ->and($batch->valid_rows)->toBe(1)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe(0)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});

test('registrar consents to applicant intake and guidance separately commits scores', function (): void {
    $env = interofficeApprovalEnvironment();
    $guidance = interofficeApprovalActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $registrar = interofficeApprovalActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $batch = stageGuidanceAllInOneBatch($this, $env['cycle'], $guidance);
    $rowIds = $batch->rows()->where('status', 'valid')->pluck('id')->all();
    $this->actingAs($registrar)
        ->from(route('imports.index'))
        ->post(route('imports.approve', $batch), ['selected_row_ids' => []])
        ->assertSessionHasErrors('selected_row_ids');

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_PENDING_APPROVAL)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $this->actingAs($registrar)
        ->from(route('imports.index'))
        ->post(route('imports.approve', $batch), ['selected_row_ids' => $rowIds])
        ->assertRedirect(route('imports.index'));

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_AWAITING_GUIDANCE)
        ->and($batch->rows()->first()->fresh()->status)->toBe(ImportBatchRow::STATUS_APPLICANT_IMPORTED)
        ->and(ApplicantIdentity::query()->where('first_name', 'Maria')->count())->toBe(1)
        ->and(AdmissionAttempt::query()->where('academic_cycle_id', $env['cycle']->id)->count())->toBe(1)
        ->and(AdmissionAttempt::query()->where('academic_cycle_id', $env['cycle']->id)->value('privacy_policy_id'))->toBeNull()
        ->and(AdmissionAttempt::query()->where('academic_cycle_id', $env['cycle']->id)->value('public_policy_id'))->toBeNull()
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $this->actingAs($registrar)
        ->post(route('imports.resume', $batch))
        ->assertForbidden();

    $this->actingAs($guidance)
        ->from("/imports/{$batch->id}/review")
        ->post(route('imports.resume', $batch), ['selected_row_ids' => []])
        ->assertSessionHasErrors('selected_row_ids');

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_AWAITING_GUIDANCE)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $this->actingAs($guidance)
        ->from("/imports/{$batch->id}/review")
        ->post(route('imports.resume', $batch))
        ->assertRedirect("/imports/{$batch->id}/review");

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and(ExamAssignment::query()->count())->toBe(1)
        ->and(ScoreRecord::query()->count())->toBe(2);

    $this->actingAs($guidance)
        ->post(route('imports.resume', $batch))
        ->assertRedirect("/imports/{$batch->id}/review");

    expect(ScoreRecord::query()->count())->toBe(2);
});

test('registrar can reject pending batch with reason', function (): void {
    $env = interofficeApprovalEnvironment();
    $guidance = interofficeApprovalActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $registrar = interofficeApprovalActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);
    $batch = stageGuidanceAllInOneBatch($this, $env['cycle'], $guidance);

    $this->actingAs($registrar)
        ->from(route('imports.index'))
        ->post(route('imports.reject', $batch), ['reason' => 'Incorrect examinee data'])
        ->assertRedirect(route('imports.index'));
    $this->actingAs($registrar)
        ->from(route('imports.index'))
        ->post(route('imports.reject', $batch), ['reason' => 'Incorrect examinee data'])
        ->assertRedirect(route('imports.index'));

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_REJECTED)
        ->and(AuditEvent::query()->where('event_type', 'import.batch_rejected')->count())->toBe(1)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});

test('registrar cycle delegation lets guidance complete combined intake without consent', function (): void {
    $env = interofficeApprovalEnvironment(delegated: true);
    $guidance = interofficeApprovalActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $batch = stageGuidanceAllInOneBatch($this, $env['cycle'], $guidance);
    $rowIds = $batch->rows()->where('status', 'valid')->pluck('id')->all();

    expect($batch->status)->toBe(ImportBatch::STATUS_STAGED)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $this->actingAs($guidance)
        ->from("/imports/{$batch->id}/review")
        ->post(route('imports.commit', $batch), ['selected_row_ids' => $rowIds])
        ->assertRedirect("/imports/{$batch->id}/review");

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and(ApplicantIdentity::query()->where('first_name', 'Maria')->count())->toBe(1)
        ->and(ExamAssignment::query()->count())->toBe(1)
        ->and(ScoreRecord::query()->count())->toBe(2);
});

test('guidance cannot approve pending batch without registrar capability', function (): void {
    $env = interofficeApprovalEnvironment();
    $guidance = interofficeApprovalActor([CapabilityCatalog::GUIDANCE_SCORING_IMPORT]);
    $batch = stageGuidanceAllInOneBatch($this, $env['cycle'], $guidance);

    $this->actingAs($guidance)
        ->post(route('imports.approve', $batch))
        ->assertForbidden();

    expect($batch->fresh()->status)->toBe(ImportBatch::STATUS_PENDING_APPROVAL)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe(0)
        ->and(ExamAssignment::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});
test('registrar-only user cannot stage a score-bearing combined import', function (): void {
    $env = interofficeApprovalEnvironment();
    $registrar = interofficeApprovalActor([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT]);

    $csv = implode("\n", [
        'first_name,last_name,birthdate,sex,course,email,strand,school,math,science',
        'Maria,Santos,2005-04-12,female,BSCS,maria@example.test,STEM,North High,45,42',
    ]);
    $file = UploadedFile::fake()->createWithContent('examinees.csv', $csv);

    $this->actingAs($registrar)
        ->post(route('imports.stage'), [
            'file' => $file,
            'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
            'academic_cycle_id' => $env['cycle']->id,
        ])
        ->assertForbidden();

    expect(ImportBatch::query()->count())->toBe(0)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});
