<?php

namespace App\Examination\Queries;

final readonly class SessionSearchSuggestion
{
    /**
     * @param  'examinee'|'session'|'waitlist_applicant'|'pipeline_applicant'  $kind
     * @param  list<string>  $matchedFields
     * @param  array{url: string, focus_kind: 'assignment'|'attempt'|null, focus_id: int|null, score_url?: string, roster_url?: string, route_name?: string}  $destination
     * @param  'scheduled'|'waitlist_ready'|'not_ready'|null  $pipelineState
     * @param  'emerald'|'amber'|'slate'|'primary'|'destructive'|null  $pipelineBadgeTone
     * @param  'sessions'|'waitlist'|null  $targetTab
     * @param  'ready'|'not_ready'|null  $readinessBucket
     */
    public function __construct(
        public string $key,
        public string $kind,
        public ?int $assignmentId,
        public ?int $attemptId,
        public ?string $referenceCode,
        public ?string $name,
        public int $examSessionId,
        public string $sessionCode,
        public string $sessionStatus,
        public string $roomLabel,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?int $gradingSessionId,
        public array $matchedFields,
        public array $destination,
        public ?string $pipelineState = null,
        public ?string $pipelineBadgeLabel = null,
        public ?string $pipelineBadgeTone = null,
        public ?string $targetTab = null,
        public ?string $readinessBucket = null,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     kind: string,
     *     entity_type: string,
     *     assignment_id: int|null,
     *     attempt_id: int|null,
     *     reference_code: string|null,
     *     name: string|null,
     *     exam_session_id: int,
     *     session_code: string,
     *     session_status: string,
     *     room_label: string,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     grading_session_id: int|null,
     *     matched_fields: list<string>,
     *     destination: array{url: string, focus_kind: 'assignment'|'attempt'|null, focus_id: int|null, score_url?: string, roster_url?: string, route_name?: string},
     *     pipeline_state: string|null,
     *     pipeline_badge_label: string|null,
     *     pipeline_badge_tone: string|null,
     *     target_tab: string|null,
     *     readiness_bucket: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'kind' => $this->kind,
            'assignment_id' => $this->assignmentId,
            'attempt_id' => $this->attemptId,
            'reference_code' => $this->referenceCode,
            'name' => $this->name,
            'exam_session_id' => $this->examSessionId,
            'session_code' => $this->sessionCode,
            'session_status' => $this->sessionStatus,
            'room_label' => $this->roomLabel,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'grading_session_id' => $this->gradingSessionId,
            'matched_fields' => $this->matchedFields,
            'destination' => $this->destination,
            'entity_type' => $this->kind,
            'pipeline_state' => $this->pipelineState,
            'pipeline_badge_label' => $this->pipelineBadgeLabel,
            'pipeline_badge_tone' => $this->pipelineBadgeTone,
            'target_tab' => $this->targetTab,
            'readiness_bucket' => $this->readinessBucket,
        ];
    }
}
