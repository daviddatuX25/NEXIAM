<?php

namespace App\Testing;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\RecordThread;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetIssue;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use App\Models\Release\ResultDocument;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

use function is_int;
use function is_string;
use function preg_match;

final class PracticeRecordGuard
{
    public function saving(Model $record): void
    {
        if ($record instanceof PracticeFixtureSet || $record instanceof PracticePreparationOperation) {
            return;
        }
        $roots = $this->roots($record);
        $workspace = app(PracticeWorkspace::class);
        if ($roots === []) {
            $configuration = $record instanceof ConsultationSession
                || str_starts_with($record::class, 'App\\Models\\Foundation\\') && ! $record instanceof AuditEvent
                || str_starts_with($record::class, 'App\\Models\\Assessment\\')
                || str_starts_with($record::class, 'App\\Models\\Reports\\')
                || str_starts_with($record::class, 'App\\Models\\ResultSheet\\');
            if ($workspace->currentId() !== null && $configuration) {
                throw new RuntimeException('Institutional configuration is read-only in a practice workspace.');
            }

            return;
        }
        $setIds = array_unique(array_map(fn (Model $root): string => $root->practice_fixture_set_id ?? '', $roots));
        if (count($setIds) > 1) {
            throw new RuntimeException('Operational and different practice sets cannot share a case/session record.');
        }
        $setId = reset($setIds) ?: null;
        if ($workspace->currentId() !== null && $setId !== $workspace->currentId()) {
            throw new RuntimeException('This case/session is outside the selected practice set.');
        }
        if ($setId !== null && PracticeFixtureSet::query()->whereKey($setId)->where('status', 'active')->doesntExist()) {
            throw new RuntimeException('Retired practice descendants cannot be changed.');
        }
    }

    /** @return list<Model> */
    public function roots(Model $record): array
    {
        if ($record instanceof Operation) {
            return $this->operationRoots($record);
        }

        $roots = $record instanceof AdmissionAttempt || $record instanceof ApplicantIdentity || $record instanceof ExamSession
            ? [$record]
            : [];
        $parents = [
            'admission_attempt_id' => AdmissionAttempt::class,
            'exam_session_id' => ExamSession::class,
            'applicant_identity_id' => ApplicantIdentity::class,
            'exam_assignment_id' => ExamAssignment::class,
            'grading_session_id' => GradingSession::class,
            'result_document_id' => ResultDocument::class,
            'record_thread_id' => RecordThread::class,
            'applicant_office_inquiry_id' => ApplicantOfficeInquiry::class,
            'program_pool_id' => ProgramPool::class,
        ];
        if ($record instanceof OmrCaptureSheet) {
            $parents['batch_id'] = OmrCaptureBatch::class;
        }
        if ($record instanceof OmrPendingCaptureSet) {
            $parents['production_batch_id'] = OmrCaptureBatch::class;
        }
        if ($record instanceof OmrCaptureSheetPage
            || $record instanceof OmrProcessingRun
            || $record instanceof OmrCaptureSheetIssue) {
            $parents['sheet_id'] = OmrCaptureSheet::class;
        }
        if ($record instanceof OmrPendingCapturePageEvidence
            || $record instanceof OmrQrDisabledPageClaim) {
            $parents['set_id'] = OmrPendingCaptureSet::class;
        }
        if ($record instanceof OmrPendingCapturePageAnalysis
            || $record instanceof OmrQrDisabledPageClaim) {
            $parents['evidence_id'] = OmrPendingCapturePageEvidence::class;
        }
        if ($record instanceof OmrBlankInstancePageClaim
            || $record instanceof OmrQrDisabledPageClaim) {
            $parents['capture_sheet_page_id'] = OmrCaptureSheetPage::class;
        }
        foreach ($parents as $key => $class) {
            $id = $record->getAttribute($key);
            if ($id === null) {
                continue;
            }
            $parent = $class::query()->find($id);
            if ($parent === null) {
                continue;
            }
            if ($parent instanceof AdmissionAttempt || $parent instanceof ApplicantIdentity || $parent instanceof ExamSession) {
                $roots[] = $parent;
            } else {
                array_push($roots, ...$this->roots($parent));
            }
        }

        return $roots;
    }

    /** @return list<Model> */
    private function operationRoots(Operation $operation): array
    {
        $payload = $operation->payload;
        if (! is_array($payload)) {
            return [];
        }

        $command = $operation->type;
        if ($command === 'admission.office-inquiry.create') {
            $attemptId = $payload['attempt_id'] ?? null;
            if (! $this->isPositiveId($attemptId)
                || ! $this->operationAggregateMatches($operation, $command, 'attempt:'.$attemptId)) {
                return [];
            }

            $attempt = AdmissionAttempt::query()->find($attemptId);

            return $attempt === null ? [] : [$attempt];
        }
        if (in_array($command, [
            'guidance.pool.set',
            'guidance.follow_up.open',
            'consultation.decision.record',
        ], true)) {
            $attemptId = $payload['attempt_id'] ?? null;
            if (! $this->isPositiveId($attemptId)
                || ! $this->operationAggregateMatches($operation, $command, 'attempt:'.$attemptId)) {
                return [];
            }

            $attempt = AdmissionAttempt::query()->find($attemptId);

            return $attempt === null ? [] : [$attempt];
        }

        if (in_array($command, ['admission.office-inquiry.send', 'admission.office-inquiry.resolve'], true)) {
            $inquiryId = $payload['inquiry_id'] ?? null;
            if (! $this->isPositiveId($inquiryId)
                || ! $this->operationAggregateMatches($operation, $command, 'inquiry:'.$inquiryId)
                || (isset($payload['receipt_inquiry_id']) && (string) $payload['receipt_inquiry_id'] !== (string) $inquiryId)) {
                return [];
            }

            $inquiry = ApplicantOfficeInquiry::query()->with('attempt')->find($inquiryId);
            if ($inquiry === null || $inquiry->attempt === null) {
                return [];
            }

            return [$inquiry->attempt];
        }

        return [];
    }

    private function operationAggregateMatches(Operation $operation, string $command, string $aggregate): bool
    {
        $parts = explode(':', (string) $operation->idempotency_key);

        return count($parts) === 5
            && $parts[1] === $command
            && $aggregate === $parts[2].':'.$parts[3];
    }

    private function isPositiveId(mixed $value): bool
    {
        return is_int($value) && $value > 0
            || is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1;
    }
}
