<?php

namespace App\Release\Actions;

use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptResultReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\ProgramHead\Support\AcademicReferralNotice;
use App\Release\Support\ReleaseDayDecisionNotice;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RecordConsultationDecision
{
    /**
     * @var list<string>
     */
    public const VALID_DECISION_TYPES = [
        'approve',
        'modify',
        'override',
        'defer',
        'refer',
    ];

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SetGuidanceProgramPool $setGuidanceProgramPool,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $commandIdempotency,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AcademicReferralNotice $academicReferralNotice,
        private readonly QueueConsultationPortalCopy $queuePortalCopy,
        private readonly ReleaseDayDecisionNotice $releaseDayDecisionNotice,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $counselor,
        string $decisionType,
        ?string $notes,
        string $idempotencyKey,
        ?string $targetProgramCode = null,
        ?string $referralReason = null,
        ?string $defaultProgramCode = null,
        ?array $poolOptions = null,
        ?string $poolReason = null,
        ?string $encounterId = null,
    ): ConsultationDecision {

        if (! in_array($decisionType, self::VALID_DECISION_TYPES, true)) {
            throw new RuntimeException("Invalid consultation decision type: {$decisionType}.");
        }
        $consultationEncounterId = $encounterId;
        $decision = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_QUOTA,
            (int) $attempt->academic_cycle_id,
            function () use (&$consultationEncounterId, $attempt, $counselor, $decisionType, $notes, $idempotencyKey, $targetProgramCode, $referralReason, $defaultProgramCode, $poolOptions, $poolReason, $encounterId): ConsultationDecision {
                return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_CONSULTATION, (int) $attempt->academic_cycle_id, fn (): ConsultationDecision => DB::transaction(function () use (&$consultationEncounterId, $attempt, $counselor, $decisionType, $notes, $idempotencyKey, $targetProgramCode, $referralReason, $defaultProgramCode, $poolOptions, $poolReason, $encounterId): ConsultationDecision {
                    $attempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                    if (($defaultProgramCode !== null || $poolOptions !== null)
                        && ! $this->authorizer->allows($counselor, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)) {
                        throw new RuntimeException('You are not authorized to manage Guidance program selections.');
                    }

                    if ($decisionType === 'refer') {
                        if (! $this->authorizer->allows($counselor, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)) {
                            throw new RuntimeException('You are not authorized to manage Guidance program selections.');
                        }

                        if (! $this->authorizer->allows($counselor, CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE)) {
                            throw new RuntimeException('You are not authorized to manage Guidance program follow-ups.');
                        }
                    }

                    $this->commandIdempotency->claim(
                        $counselor,
                        'consultation.decision.record',
                        'attempt:'.$attempt->id,
                        [
                            'decision_type' => $decisionType,
                            'notes' => $notes,
                            'target_program_code' => $targetProgramCode,
                            'referral_reason' => $referralReason,
                            'default_program_code' => $defaultProgramCode,
                            'pool_options' => $poolOptions,
                            'pool_reason' => $poolReason,
                        ],
                        $idempotencyKey,
                    );

                    $existing = ConsultationDecision::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        if ((int) $existing->admission_attempt_id !== (int) $attempt->id) {
                            throw new RuntimeException('Idempotency key has already been used for a different admission attempt.');
                        }

                        if ($consultationEncounterId === null) {
                            $consultationEncounterId = ConsultationEncounter::query()
                                ->where('idempotency_key', 'consultation-decision-encounter-'.$existing->idempotency_key)
                                ->value('id');
                        }

                        return $existing;
                    }
                    if (AttemptProgramFacts::isEnrolling($attempt) || AttemptProgramFacts::isEnrolled($attempt)) {
                        throw new RuntimeException('Cannot record a consultation decision after enrollment has started or completed.');
                    }

                    if ($decisionType === 'defer' && AttemptProgramFacts::currentSelection($attempt) !== null) {
                        throw new RuntimeException('The active program selection must be resolved before deferring consultation.');
                    }

                    if ($decisionType === 'refer') {
                        if (blank($targetProgramCode) || blank($referralReason)) {
                            throw new RuntimeException('A target program and referral reason are required for a referral.');
                        }

                        $referralReason = trim($referralReason);
                        if (mb_strlen($referralReason) > 500) {
                            throw new RuntimeException('Referral reason must not exceed 500 characters.');
                        }
                        $referralProgram = Program::query()
                            ->where('code', $targetProgramCode)
                            ->where('institution_id', $attempt->institution_id)
                            ->where('is_active', true)
                            ->first();
                        if ($referralProgram === null) {
                            throw new RuntimeException('The referral target program is not active at the admission attempt institution.');
                        }

                    }

                    if (in_array($decisionType, ['override', 'defer'], true) && blank($notes)) {
                        throw new RuntimeException('Notes are required for override and defer decisions.');
                    }
                    if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                        throw new RuntimeException('Only accepted admission attempts may be consulted.');
                    }
                    if (! AttemptResultReadiness::isReady($attempt)) {
                        throw new RuntimeException('Consultation decision requires a frozen individual result and prepared document.');
                    }

                    $activeAssignment = ConsultationAssignment::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
                        ->lockForUpdate()
                        ->first();

                    $encounter = ConsultationEncounter::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->whereIn('status', [
                            ConsultationEncounter::STATUS_INVITED,
                            ConsultationEncounter::STATUS_WAITING,
                            ConsultationEncounter::STATUS_IN_PROGRESS,
                        ])
                        ->when($encounterId !== null, fn ($query) => $query->whereKey($encounterId))
                        ->when($encounterId === null, fn ($query) => $query->where('medium', ConsultationEncounter::MEDIUM_F2F))
                        ->lockForUpdate()
                        ->first();
                    if ($encounterId !== null && $encounter === null) {
                        throw new RuntimeException('The specified active consultation encounter was not found for this admission attempt.');
                    }
                    if ($encounter === null) {
                        $mode = ConsultationEncounter::MEDIUM_F2F;
                        $encounter = ConsultationEncounter::query()->create([
                            'institution_id' => $attempt->institution_id,
                            'admission_attempt_id' => $attempt->id,
                            'consultation_assignment_id' => $activeAssignment?->id,
                            'counselor_user_id' => $counselor->id,
                            'medium' => $mode,
                            'booking_path' => $activeAssignment === null
                                ? ConsultationEncounter::BOOKING_PATH_DIRECT
                                : ConsultationEncounter::BOOKING_PATH_SCHEDULED,
                            'status' => ConsultationEncounter::STATUS_INVITED,
                            'idempotency_key' => 'consultation-decision-encounter-'.$idempotencyKey,
                        ]);
                    } else {
                        $mode = $encounter->medium;
                        if ($mode === ConsultationEncounter::MEDIUM_ONLINE
                            && ($encounterId === null
                                || (int) $encounter->counselor_user_id !== (int) $counselor->id
                                || $encounter->status !== ConsultationEncounter::STATUS_IN_PROGRESS
                                || $encounter->admitted_at === null
                                || $encounter->access_starts_at === null
                                || $encounter->access_ends_at === null
                                || now()->lt($encounter->access_starts_at)
                                || now()->gte($encounter->access_ends_at))) {
                            throw new RuntimeException('Only the admitted host may record an outcome during the active online consultation window.');
                        }
                    }
                    $consultationEncounterId = (int) $encounter->id;

                    if ($attempt->current_result_document_id === null) {
                        $readyDocId = ResultDocument::query()
                            ->where('admission_attempt_id', $attempt->id)
                            ->where('status', ResultDocument::STATUS_READY)
                            ->latest('id')
                            ->value('id');

                        if ($readyDocId) {
                            $attempt->update(['current_result_document_id' => $readyDocId]);
                        }
                    }

                    if ($mode === ConsultationEncounter::MEDIUM_F2F) {
                        $hasPrintedResult = ResultDocumentPrintReceipt::query()
                            ->where('admission_attempt_id', $attempt->id)
                            ->where('is_current', true)
                            ->exists()
                            || ResultRelease::query()
                                ->where('admission_attempt_id', $attempt->id)
                                ->where('is_current', true)
                                ->whereNotNull('result_printed_at')
                                ->exists();
                        if (! $hasPrintedResult) {
                            throw new RuntimeException('F2F consultation requires the physical result sheet to be printed.');
                        }
                    }

                    if ($activeAssignment !== null) {
                        $activeAssignment->update([
                            'status' => ConsultationAssignment::STATUS_CONSULTED,
                            'consulted_at' => now(),
                        ]);
                        $origin = 'scheduled';
                    } else {
                        $origin = 'direct_walk_in';
                    }
                    $encounterDocument = $mode === ConsultationEncounter::MEDIUM_F2F
                        ? ResultDocument::query()->find($attempt->current_result_document_id)
                        : null;
                    $encounter->update([
                        'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                        'closed_at' => now(),
                        ...($encounterDocument === null ? [] : [
                            'result_document_id' => $encounterDocument->id,
                            'result_document_version' => $encounterDocument->version,
                        ]),
                    ]);

                    if ($decisionType === 'refer') {
                        $program = $referralProgram;

                        $followUp = ProgramFollowUp::query()
                            ->where('admission_attempt_id', $attempt->id)
                            ->where('program_id', $program->id)
                            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
                            ->lockForUpdate()
                            ->first();
                        $pool = ProgramPool::currentFor($attempt->id);
                        if ($pool === null) {
                            $pool = ProgramPool::query()->create([
                                'admission_attempt_id' => $attempt->id,
                                'version' => 1,
                                'predecessor_id' => null,
                                'actor_user_id' => $counselor->id,
                                'reason' => mb_substr((string) ($poolReason ?? 'Referral target pool'), 0, 500),
                                'source' => 'consultation_referral',
                                'idempotency_key' => $idempotencyKey.'-pool',
                                'created_at' => now(),
                            ]);
                            $pool->options()->create([
                                'program_id' => $program->id,
                                'display_order' => 1,
                                'basis' => 'referral_target',
                            ]);
                        } else {
                            $existingOptions = $pool->options()->orderBy('display_order')->get();
                            $targetIsInPool = $existingOptions->contains('program_id', $program->id);
                            $hasDeclineForCurrentPool = ProgramDecline::query()
                                ->where('admission_attempt_id', $attempt->id)
                                ->where('program_id', $program->id)
                                ->where('program_pool_id', $pool->id)
                                ->exists();

                            if (! $targetIsInPool || $hasDeclineForCurrentPool) {
                                $successor = ProgramPool::query()->create([
                                    'admission_attempt_id' => $attempt->id,
                                    'version' => $pool->version + 1,
                                    'predecessor_id' => $pool->id,
                                    'actor_user_id' => $counselor->id,
                                    'reason' => mb_substr((string) ($poolReason ?? 'Referral target program reconsidered'), 0, 500),
                                    'source' => 'consultation_referral',
                                    'idempotency_key' => $idempotencyKey.'-pool-v'.($pool->version + 1),
                                    'created_at' => now(),
                                ]);
                                foreach ($existingOptions as $opt) {
                                    $successor->options()->create([
                                        'program_id' => $opt->program_id,
                                        'display_order' => $opt->display_order,
                                        'basis' => $opt->basis,
                                    ]);
                                }

                                if (! $targetIsInPool) {
                                    $successor->options()->create([
                                        'program_id' => $program->id,
                                        'display_order' => max(0, (int) $existingOptions->max('display_order')) + 1,
                                        'basis' => 'referral_target',
                                    ]);
                                }

                                $pool = $successor;
                            }
                        }

                        if ($followUp === null) {
                            $followUp = ProgramFollowUp::query()->create([
                                'admission_attempt_id' => $attempt->id,
                                'program_id' => $program->id,
                                'program_pool_id' => $pool->id,
                                'opened_by_user_id' => $counselor->id,
                                'status' => ProgramFollowUp::STATUS_OPEN,
                                'reason' => trim($referralReason),
                                'idempotency_key' => $idempotencyKey.'-fu',
                                'version' => 1,
                                'opened_at' => now(),
                            ]);
                        } else {
                            $followUp->update([
                                'program_pool_id' => $pool->id,
                                'opened_by_user_id' => $counselor->id,
                                'status' => ProgramFollowUp::STATUS_OPEN,
                                'reason' => trim($referralReason),
                                'version' => $followUp->version + 1,
                                'opened_at' => now(),
                            ]);
                        }
                        $this->academicReferralNotice->handle($followUp);
                    }

                    $latestDecision = ConsultationDecision::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->lockForUpdate()
                        ->latest('version')
                        ->first();

                    $decision = ConsultationDecision::query()->create([
                        'admission_attempt_id' => $attempt->id,
                        'counselor_user_id' => $counselor->id,
                        'decision_type' => $decisionType,
                        'notes' => $notes,
                        'version' => ($latestDecision?->version ?? 0) + 1,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    if (in_array($decisionType, ['approve', 'modify', 'override'], true) && $defaultProgramCode !== null) {
                        if ($poolOptions === null || empty($poolOptions)) {
                            $poolOptions = [[
                                'program_code' => $defaultProgramCode,
                                'display_order' => 1,
                                'basis' => $decisionType === 'modify' ? 'guidance_option' : 'preference',
                            ]];
                        } else {
                            $defaultIsInPool = false;
                            foreach ($poolOptions as $poolOption) {
                                if ($poolOption['program_code'] === $defaultProgramCode) {
                                    $defaultIsInPool = true;

                                    break;
                                }
                            }

                            if (! $defaultIsInPool) {
                                foreach ($poolOptions as &$poolOption) {
                                    $poolOption['display_order']++;
                                }
                                unset($poolOption);

                                array_unshift($poolOptions, [
                                    'program_code' => $defaultProgramCode,
                                    'display_order' => 1,
                                    'basis' => $decisionType === 'modify' ? 'guidance_option' : 'preference',
                                ]);
                            }
                        }

                        $this->setGuidanceProgramPool->handle(
                            $attempt,
                            $counselor,
                            $defaultProgramCode,
                            $poolOptions,
                            mb_substr((string) ($poolReason ?? 'Consultation decision'), 0, 500),
                            $idempotencyKey.'-pool',
                        );
                    }

                    $this->recordAuditEvent->handle('consultation.decision_recorded', [
                        'attempt_id' => $attempt->id,
                        'decision_id' => $decision->id,
                        'decision_type' => $decisionType,
                        'origin' => $origin,
                        'assignment_id' => $activeAssignment?->id,
                    ], (string) $counselor->id);
                    $this->releaseDayDecisionNotice->handle($attempt, $decision);

                    return $decision->fresh();
                }));
            },
        );

        if (in_array($decision->decision_type, ['approve', 'modify', 'override', 'refer'], true)) {
            DB::afterCommit(function () use ($attempt, $decision, $counselor, $consultationEncounterId): void {
                $attemptId = (int) $attempt->id;
                try {
                    $attempt = AdmissionAttempt::query()->find($attemptId);
                    $encounter = ConsultationEncounter::query()
                        ->where('admission_attempt_id', $attempt?->id)
                        ->when(
                            $consultationEncounterId !== null,
                            fn ($query) => $query->whereKey($consultationEncounterId),
                            fn ($query) => $query->where('idempotency_key', 'consultation-decision-encounter-'.$decision->idempotency_key),
                        )
                        ->first();
                    $documentId = $encounter?->result_document_id ?? $attempt?->current_result_document_id;
                    $document = $documentId === null ? null : ResultDocument::query()->find($documentId);

                    if ($attempt !== null && $encounter !== null && $document !== null) {
                        $this->queuePortalCopy->handle($attempt, $decision, $encounter, $document, $counselor);
                    }
                } catch (\Throwable $exception) {
                    Log::error('Automatic consultation portal result issuance could not be queued: '.$exception->getMessage(), [
                        'decision_id' => $decision->id,
                        'attempt_id' => $attemptId,
                        'exception' => $exception,
                    ]);
                }
            });
        }

        return $decision;
    }
}
