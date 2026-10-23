<?php

declare(strict_types=1);

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Queries\ReleaseOverviewQuery;
use App\ResultSheet\Queries\ResultSheetTemplatesListQuery;
use Illuminate\Support\Str;

test('Xiam-safe result template samples match the human list without exposing template content', function (): void {
    seedE2Foundation();
    ResultSheetTemplate::query()->delete();

    foreach ([
        ['name' => 'Official Result Sheet', 'active' => true],
        ['name' => 'Archived Result Sheet', 'active' => false],
        ['name' => 'Draft Results Layout', 'active' => false],
        ['name' => 'Supplemental Result Sheet', 'active' => false],
        ['name' => 'Transcript Result Sheet', 'active' => false],
    ] as $template) {
        ResultSheetTemplate::query()->create([
            'name' => $template['name'],
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'content' => '<div>PRIVATE_TEMPLATE_CONTENT_'.$template['name'].'</div>',
            'builder_json' => ['private_builder_payload' => 'PRIVATE_BUILDER_DATA_'.$template['name']],
            'document_path' => 'private/templates/'.$template['name'].'.docx',
            'watermark_text' => 'staff-only watermark',
            'is_active' => $template['active'],
        ]);
    }

    $query = app(ResultSheetTemplatesListQuery::class);
    $humanTemplates = $query->forHumanIndex();
    $xiamProjection = $query->forXiamSafe(limit: 2);
    $humanById = $humanTemplates->keyBy('id');
    $sample = collect($xiamProjection['templates']);
    $sampleParity = $sample->map(function (array $safeTemplate) use ($humanById): array {
        $humanTemplate = $humanById->get($safeTemplate['id']);

        return [
            'display_name' => is_array($humanTemplate) ? $humanTemplate['name'] : $humanTemplate?->name,
            'is_active' => is_array($humanTemplate) ? (bool) $humanTemplate['is_active'] : (bool) $humanTemplate?->is_active,
            'mode' => is_array($humanTemplate) ? $humanTemplate['mode'] : $humanTemplate?->mode,
            'orientation' => is_array($humanTemplate) ? $humanTemplate['orientation'] : $humanTemplate?->orientation,
            'paper_size' => is_array($humanTemplate) ? $humanTemplate['paper_size'] : $humanTemplate?->paper_size,
        ];
    })->all();
    $safeJson = json_encode($xiamProjection, JSON_THROW_ON_ERROR);

    expect($humanTemplates)->toHaveCount(5)
        ->and($xiamProjection['summary']['total_count'])->toBe(5)
        ->and($xiamProjection['summary']['active_count'])->toBe(1)
        ->and($xiamProjection['summary']['active_template_name'])->toBe('Official Result Sheet')
        ->and($sample)->toHaveCount(2)
        ->and($sample->pluck('display_name')->all())->toBe(['Official Result Sheet', 'Archived Result Sheet'])
        ->and($sampleParity)->toBe($sample->map(fn (array $safeTemplate): array => [
            'display_name' => $safeTemplate['display_name'],
            'is_active' => $safeTemplate['is_active'],
            'mode' => $safeTemplate['mode'],
            'orientation' => $safeTemplate['orientation'],
            'paper_size' => $safeTemplate['paper_size'],
        ])->all())
        ->and(is_array($humanTemplates->firstWhere('name', 'Official Result Sheet')) ? $humanTemplates->firstWhere('name', 'Official Result Sheet')['is_active'] : $humanTemplates->firstWhere('name', 'Official Result Sheet')->is_active)->toBeTrue()
        ->and($safeJson)->not->toContain(
            'content',
            'builder_json',
            'document_path',
            'PRIVATE_TEMPLATE_CONTENT',
            'PRIVATE_BUILDER_DATA',
            'private/templates/',
            'staff-only watermark',
        );
});

test('Xiam release overview totals cover all scoped sessions and attempts before sampling', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $areaId = AptitudeArea::query()->where('is_active', true)->orderBy('id')->value('id');
    $attempts = collect([
        submitSyntheticApplicant('release-parity-1@example.test', 'ReleaseParityOne')['attempt'],
        submitSyntheticApplicant('release-parity-2@example.test', 'ReleaseParityTwo')['attempt'],
        submitSyntheticApplicant('release-parity-3@example.test', 'ReleaseParityThree')['attempt'],
    ]);

    foreach ($attempts as $index => $attempt) {
        $examSession = ExamSession::query()->create([
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-PARITY-LOCAL-'.($index + 1),
            'status' => ExamSession::STATUS_COMPLETED,
            'starts_at' => now()->subDays(4 - $index),
            'effective_end_at' => now()->subDays(4 - $index)->addHours(2),
            'version' => 1,
        ]);
        $gradingSession = GradingSession::query()->create([
            'exam_session_id' => $examSession->id,
            'status' => GradingSession::STATUS_FINALIZED,
            'opened_at' => now()->subDays(4 - $index),
            'finalized_at' => now()->subDays(3 - $index),
            'version' => 1,
        ]);
        ScoreRecord::query()->create([
            'grading_session_id' => $gradingSession->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $areaId,
            'score' => 80 + $index,
            'idempotency_key' => 'release-parity-score-'.$attempt->id,
        ]);

        if ($index < 2) {
            $document = ResultDocument::query()->create([
                'admission_attempt_id' => $attempt->id,
                'template_version' => 'release-parity-fixture',
                'checksum' => hash('sha256', 'release-parity-document-'.$attempt->id),
                'status' => ResultDocument::STATUS_READY,
                'version' => 1,
            ]);
            ResultRelease::query()->create([
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'released_by_user_id' => $guidance->id,
                'release_version' => 1,
                'is_current' => true,
                'released_at' => now(),
                'idempotency_key' => 'release-parity-release-'.$attempt->id,
            ]);
        }
    }

    $foreignInstitution = Institution::query()->create([
        'code' => 'PARITY-FOREIGN-'.Str::upper(Str::random(8)),
        'name' => 'Foreign parity institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'PARITY-FOREIGN-CYCLE-'.Str::upper(Str::random(8)),
        'name' => 'Foreign parity cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
    ]);
    $foreignAttempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => ApplicantIdentity::query()->create([
            'institution_id' => $foreignInstitution->id,
            'normalized_key' => hash('sha256', 'release-parity-foreign-applicant'),
            'first_name' => 'Foreign',
            'last_name' => 'Applicant',
            'birthdate' => '2001-01-01',
            'sex' => 'male',
        ])->id,
        'institution_id' => $foreignInstitution->id,
        'academic_cycle_id' => $foreignCycle->id,
        'reference_code' => 'APP-PARITY-FOREIGN',
        'status' => AdmissionAttempt::STATUS_PENDING,
        'version' => 1,
    ]);
    $foreignExamSession = ExamSession::query()->create([
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PARITY-FOREIGN',
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDay(),
        'effective_end_at' => now()->subDay()->addHours(2),
        'version' => 1,
    ]);
    $foreignGradingSession = GradingSession::query()->create([
        'exam_session_id' => $foreignExamSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDay(),
        'finalized_at' => now(),
        'version' => 1,
    ]);
    ScoreRecord::query()->create([
        'grading_session_id' => $foreignGradingSession->id,
        'admission_attempt_id' => $foreignAttempt->id,
        'aptitude_area_id' => $areaId,
        'score' => 100,
        'idempotency_key' => 'release-parity-foreign-score-'.$foreignAttempt->id,
    ]);

    $projection = app(ReleaseOverviewQuery::class)->forXiamSafe((int) $institution->id, sampleLimit: 2);

    expect($projection['summary']['total_grading_sessions'])->toBe(3)
        ->and($projection['summary']['total_examinees'])->toBe(3)
        ->and($projection['summary']['total_released'])->toBe(2)
        ->and($projection['sessions'])->toHaveCount(2)
        ->and(collect($projection['sessions'])->pluck('exam_code')->all())->not->toContain('EX-PARITY-FOREIGN')
        ->and(collect($projection['sessions'])->pluck('exam_code')->every(fn (string $code): bool => str_starts_with($code, 'EX-PARITY-LOCAL-')))->toBeTrue();
});
