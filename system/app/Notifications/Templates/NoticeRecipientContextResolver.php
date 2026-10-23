<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Notifications\NotificationIntent;

final class NoticeRecipientContextResolver
{
    /** @return array{institution_id:int,values:array<string,string>} */
    public function reminder(ExamAssignment $assignment, ExamSession $session): array
    {
        $assignment->loadMissing(['admissionAttempt.identity', 'activeSeatAllocation.sessionSeat.materialSnapshot']);
        $session->loadMissing(['academicCycle.institution', 'room', 'currentMaterialSnapshot']);
        $attempt = $assignment->admissionAttempt;
        $identity = $attempt?->identity;
        $institutionId = (int) $session->academicCycle?->institution_id;
        if ($institutionId < 1 || $identity === null || (int) $identity->institution_id !== $institutionId
            || (int) $attempt->institution_id !== $institutionId
            || (int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id
            || (int) $assignment->exam_session_id !== (int) $session->id) {
            throw new \RuntimeException('notice_recipient_resource_mismatch');
        }
        $allocation = $assignment->activeSeatAllocation;
        $seat = $allocation?->sessionSeat;
        $material = $seat?->materialSnapshot;
        $values = [
            'applicant_name' => $identity->fullName(),
            'session_code' => $session->code,
            'exam_date' => $session->starts_at?->format('F j, Y'),
            'exam_time' => $session->starts_at?->format('g:i A'),
            'room_name' => (int) $session->room?->institution_id === $institutionId ? $session->room?->name : null,
            'institution_name' => $session->academicCycle?->institution?->name,
            'portal_url' => rtrim((string) config('app.url'), '/').route('applicant.schedule', absolute: false),
        ];
        if ($assignment->isActive() && $allocation !== null && $allocation->effective_to === null
            && (int) $allocation->exam_assignment_id === (int) $assignment->id
            && $material?->is_current
            && $material->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            && (int) $material->exam_session_id === (int) $session->id
            && (int) $session->currentMaterialSnapshot?->id === (int) $material->id
            && $seat !== null && (int) $seat->material_snapshot_id === (int) $material->id) {
            $values['seat_number'] = $seat->seat_label;
        }

        return ['institution_id' => $institutionId, 'values' => $this->allowlisted('examination', 'reminder', $values)];
    }

    /** @return array{institution_id:int,values:array<string,string>,resource:array<string,mixed>} */
    public function forReminder(NotificationIntent $intent, ApplicantIdentity $recipient): array
    {
        $query = ExamAssignment::query()->where('exam_session_id', $intent->payload['session_id'] ?? null)
            ->where('status', ExamAssignment::STATUS_ASSIGNED)
            ->whereHas('admissionAttempt', fn ($query) => $query->where('applicant_identity_id', $recipient->id));
        if (isset($intent->payload['assignment_id'])) {
            $query->whereKey($intent->payload['assignment_id']);
        }
        $assignment = $query->firstOrFail();
        $session = ExamSession::query()->findOrFail($assignment->exam_session_id);

        $context = $this->reminder($assignment, $session);
        $generation = $session->notification_policy_snapshot['session_version'] ?? $session->version;
        $context['resource'] = ['id' => $session->id, 'version' => $generation, 'generation' => $generation,
            'window' => $intent->payload['window'] ?? null, 'assignment_id' => $assignment->id,
            'assignment_version' => $assignment->version, 'starts_at' => $session->starts_at?->toIso8601String()];

        return $context;
    }

    /** @return array<string,string> */
    public function allowlisted(string $domain, string $slug, array $values): array
    {
        $allowed = array_unique([
            ...array_map(static fn (array $token): string => trim($token['token'], '{} '),
                CommunicationTemplateRegistry::findBySlug($domain, $slug)['tokens'] ?? []),
            ...CommunicationTemplateRegistry::builtinContextKeys($domain, $slug),
        ]);
        $actual = [];
        foreach ($allowed as $key) {
            $value = $values[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== ''
                && ! str_contains((string) $value, '{{') && ! str_contains((string) $value, '}}')) {
                $actual[$key] = (string) $value;
            }
        }

        return $actual;
    }

    /** @return list<string> */
    public function missingRequired(string $subject, array $blueprint, array $values): array
    {
        $required = [];
        $walk = function (mixed $node, ?string $key = null) use (&$walk, &$required): void {
            if (is_array($node)) {
                foreach ($node as $childKey => $child) {
                    $walk($child, is_string($childKey) ? $childKey : null);
                }
            } elseif (is_string($node)) {
                if ($key === 'token') {
                    $required[] = $node;
                }
                preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $node, $matches);
                foreach ($matches[1] as $token) {
                    $required[] = trim($token);
                }
            }
        };
        $walk($subject);
        $walk($blueprint);
        $missing = array_values(array_diff(array_unique($required), array_keys($values)));
        sort($missing);

        return $missing;
    }
}
