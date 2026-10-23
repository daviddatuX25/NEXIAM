<?php

namespace App\Release\Actions;

use App\AdmissionEngine\Jobs\PrecomputeApplicantCmiJob;
use App\Assessment\Actions\FreezeAssessmentResultSet;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\DocxTextExtractor;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Produces the template-backed, checksummed, privately stored ResultDocument
 * that ReleaseResult consumes. The active result-sheet template drives the
 * artifact (HTML content filled with the applicant's released scoring data;
 * DOCX templates produce a filled text preview). A document is only marked
 * ready after successful generation — failures throw and never fake-ready.
 */
class PrepareResultDocument
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ResultSheetDataProvider $dataProvider,
        private readonly ResultSheetPlaceholderService $placeholders,
        private readonly ResultSheetCssService $css,
        private readonly DocxTextExtractor $extractor,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AutoAssignEligibleAttempt $autoAssign,
        private readonly IndividualScoringReadiness $individualScoringReadiness,
        private readonly FreezeAssessmentResultSet $freezeAssessmentResultSet,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, string $templateVersion = 'e2-v1'): ResultDocument
    {
        $document = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $actor, $templateVersion): ResultDocument {
                $assignment = ExamAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $attempt->id)
                    ->orderByDesc('id')
                    ->first();
                if ($assignment === null) {
                    throw new RuntimeException('Active exam assignment required before preparing result document.');
                }

                $grading = GradingSession::query()
                    ->where('exam_session_id', $assignment->exam_session_id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
                if ($grading === null) {
                    throw new RuntimeException('Grading session required before preparing result document.');
                }

                $attempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $assignment = ExamAssignment::query()
                    ->active()
                    ->whereKey($assignment->id)
                    ->where('admission_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->first();
                if ($assignment === null || (int) $assignment->exam_session_id !== (int) $grading->exam_session_id) {
                    throw new RuntimeException('The selected exam sitting changed before result preparation.');
                }

                $administrations = AssessmentAdministration::query()
                    ->where('exam_assignment_id', $assignment->id)
                    ->active()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($administrations->count() > 1) {
                    throw new RuntimeException('The exam assignment has ambiguous active assessment administrations.');
                }
                $administration = $administrations->first();
                $readiness = $this->individualScoringReadiness->forAttempt($attempt);
                if (! $readiness['ready']
                    || (int) $readiness['exam_session_id'] !== (int) $assignment->exam_session_id
                    || (int) $readiness['grading_session_id'] !== (int) $grading->id
                    || ($administration !== null && (int) $readiness['administration_id'] !== (int) $administration->id)) {
                    throw new RuntimeException($readiness['reason'] ?? 'Current individual scoring is not complete for this exam sitting.');
                }

                $resultSet = null;
                if ($administration !== null) {
                    $resultSet = $this->freezeAssessmentResultSet->handle($administration, $grading, $actor);
                    if ($readiness['result_set_id'] !== null
                        && (int) $readiness['result_set_id'] !== (int) $resultSet->id) {
                        throw new RuntimeException('The frozen score snapshot changed before result preparation.');
                    }
                } elseif ($grading->status !== GradingSession::STATUS_FINALIZED) {
                    throw new RuntimeException('This legacy score record cannot be frozen before batch grading is finalized.');
                }

                $template = ResultSheetTemplate::active();

                if ($template === null) {
                    throw new RuntimeException('No active result sheet template.');
                }

                $versionTag = 'template:'.$template->id;

                $data = $this->dataProvider->forAttempt($attempt, $resultSet);
                if ($resultSet !== null) {
                    $data['_assessment_result_set_id'] = $resultSet->id;
                }
                $replacements = $this->placeholders->buildReplacements([$data]);
                $templateSourceBytes = (string) $template->content;

                if ($template->mode === ResultSheetTemplate::MODE_DOCX && $template->document_path !== null) {
                    $sourcePath = Storage::disk('local')->path($template->document_path);

                    if (! is_file($sourcePath)) {
                        throw new RuntimeException('Template document file not found on disk.');
                    }

                    $templateSourceBytes = file_get_contents($sourcePath);
                    if ($templateSourceBytes === false) {
                        throw new RuntimeException('Template document file could not be read.');
                    }

                    $text = $this->extractor->text($sourcePath);
                    $html = $this->placeholders->renderRaw('<pre class="docx-preview">'.e($text).'</pre>', $replacements);
                } else {
                    $html = $this->placeholders->renderRaw((string) $template->content, $replacements);
                }

                $snapshotPayload = $data + [
                    '_template' => [
                        'mode' => $template->mode,
                        'source_kind' => $template->source_kind,
                        'paper_size' => $template->paper_size,
                        'orientation' => $template->orientation,
                        'watermark_text' => $template->watermark_text,
                        'builder_json' => $template->builder_json,
                    ],
                    '_template_source' => [
                        'bytes_base64' => base64_encode($templateSourceBytes),
                        'sha256' => hash('sha256', $templateSourceBytes),
                    ],
                ];

                $html = $this->css->wrapForPdf($html, $template->paper_size, $template->orientation);

                $checksum = hash('sha256', $html);
                $path = 'results/'.$attempt->id.'/'.$checksum.'.html';

                $existing = ResultDocument::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('status', ResultDocument::STATUS_READY)
                    ->where('checksum', $checksum)
                    ->where(function ($query) use ($versionTag, $templateVersion) {
                        $query->where('template_version', $versionTag)
                            ->orWhere('template_version', $templateVersion)
                            ->orWhere('template_version', $versionTag.':'.$templateVersion);
                    })
                    ->get()
                    ->first(static function (ResultDocument $candidate) use ($resultSet): bool {
                        $candidateResultSetId = (int) ($candidate->snapshot_payload_json['_assessment_result_set_id'] ?? 0);

                        return $candidateResultSetId === (int) ($resultSet?->id ?? 0);
                    });

                if ($existing !== null) {
                    if ((string) $attempt->current_result_document_id !== (string) $existing->id) {
                        $attempt->forceFill(['current_result_document_id' => $existing->id])->save();
                    }

                    return $existing;
                }

                $disk = Storage::disk('local');
                $pathAlreadyExisted = $disk->exists($path);
                if (! $disk->put($path, $html)) {
                    throw new RuntimeException('Prepared result document could not be stored.');
                }

                try {
                    $document = ResultDocument::query()->create([
                        'admission_attempt_id' => $attempt->id,
                        'template_version' => $versionTag,
                        'checksum' => $checksum,
                        'status' => ResultDocument::STATUS_READY,
                        'storage_path' => $path,
                        'preview_path' => $path,
                        'snapshot_payload_json' => $snapshotPayload,
                        'artifact_manifest' => [
                            'pdf' => [
                                'status' => 'pending',
                            ],
                        ],
                    ]);
                    $attempt->forceFill(['current_result_document_id' => $document->id])->save();

                    $this->recordAuditEvent->handle('release.document_prepared', [
                        'document_id' => $document->id,
                        'attempt_id' => $attempt->id,
                        'template_id' => $template->id,
                        'checksum' => $checksum,
                        'result_set_id' => $resultSet?->id,
                    ], (string) $actor->id);
                } catch (\Throwable $exception) {
                    if (! $pathAlreadyExisted) {
                        $disk->delete($path);
                    }
                    throw $exception;
                }

                return $document;

            },
        );

        DB::afterCommit(function () use ($attempt, $actor): void {
            PrecomputeApplicantCmiJob::dispatch((int) $attempt->id)->afterCommit();
            try {
                $this->autoAssign->handle(AssignmentInvariant::DOMAIN_CONSULTATION, $attempt, $actor);
            } catch (\Throwable $exception) {
                Log::error('Consultation auto-assign post-result-document failed: '.$exception->getMessage(), ['exception' => $exception]);
            }
        });

        return $document;
    }
}
