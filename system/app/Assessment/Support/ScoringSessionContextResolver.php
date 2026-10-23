<?php

namespace App\Assessment\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use RuntimeException;

final class ScoringSessionContextResolver
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function fromRoute(Request $request): ExamSession|GradingSession
    {
        $gradingSession = $request->route('gradingSession');
        if ($gradingSession !== null) {
            return $gradingSession instanceof GradingSession
                ? $gradingSession
                : GradingSession::query()->findOrFail((int) $gradingSession);
        }

        $examSession = $request->route('examSession');

        return $examSession instanceof ExamSession
            ? $examSession
            : ExamSession::query()->findOrFail((int) $examSession);
    }

    public function resolveForRead(ExamSession $examSession, ?User $actor = null, ?string $capability = null): ?GradingSession
    {

        if ($actor !== null) {
            $sessionInstitutionId = (int) ($examSession->academicCycle?->institution_id ?? $examSession->room?->institution_id);
            $actorInstitutionId = (int) ($actor->staffProfile?->institution_id ?? Institution::current()?->id);
            if ($sessionInstitutionId !== 0 && $actorInstitutionId !== 0 && $sessionInstitutionId !== $actorInstitutionId) {
                abort(404, 'Exam session not found.');
            }

            $scope = [
                'office' => 'guidance',
                'institution_id' => $sessionInstitutionId,
            ];

            $canRead = $capability !== null
                ? $this->authorizer->decideFresh($actor, $capability, $scope)->allowed
                : ($this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_MANUAL, $scope)->allowed
                    || $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, $scope)->allowed);

            if (! $canRead) {
                throw new AuthorizationException('User does not have a scoring read capability for this exam session.');
            }
        }
        if ($examSession->status !== ExamSession::STATUS_COMPLETED) {
            throw new RuntimeException('Scoring context is only available for completed exam sessions.');
        }

        return GradingSession::query()
            ->where('exam_session_id', $examSession->getKey())
            ->first();
    }

    public function requireForWrite(ExamSession $examSession, User $actor, string $capability): GradingSession
    {
        $sessionInstitutionId = (int) ($examSession->academicCycle?->institution_id ?? $examSession->room?->institution_id);
        $actorInstitutionId = (int) ($actor->staffProfile?->institution_id ?? Institution::current()?->id);
        if ($sessionInstitutionId !== 0 && $actorInstitutionId !== 0 && $sessionInstitutionId !== $actorInstitutionId) {
            abort(404, 'Exam session not found.');
        }

        if ($examSession->status !== ExamSession::STATUS_COMPLETED) {
            throw new RuntimeException('Scoring context is only available for completed exam sessions.');
        }
        $scope = [
            'office' => 'guidance',
            'institution_id' => (int) ($examSession->academicCycle?->institution_id ?? $examSession->room?->institution_id),
        ];
        $decision = $this->authorizer->decideFresh($actor, $capability, $scope);

        if (! $decision->allowed) {
            throw new AuthorizationException("User does not have required capability: {$capability}");
        }

        $gradingSession = GradingSession::query()
            ->where('exam_session_id', $examSession->getKey())
            ->first();

        if ($gradingSession === null) {
            throw new RuntimeException('Grading session not found for this exam session.');
        }

        return $gradingSession;
    }
}
