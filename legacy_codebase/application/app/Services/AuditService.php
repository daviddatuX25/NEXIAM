<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Keys that must never be stored in old_values/new_values.
     * Per 05-SECURITY-CONTROLS §7.
     */
    protected static array $redactKeys = [
        'password',
        'password_confirmation',
        'token',
        'setup_token',
        'remember_token',
        'api_token',
    ];

    /**
     * Map of technical event names to human-readable labels.
     * Used for UI display and auto-summary generation.
     *
     * Format: 'event_prefix.action' => ['label' => 'User-friendly label', 'action' => 'past tense verb']
     */
    protected static array $eventLabels = [
        // ── Authentication events ────────────────────────────────────────
        'user.login' => ['label' => 'Staff login', 'action' => 'logged in'],
        'user.login_failed' => ['label' => 'Failed staff login', 'action' => 'failed to log in'],
        'user.logout' => ['label' => 'Staff logout', 'action' => 'logged out'],
        'user.created' => ['label' => 'User created', 'action' => 'created a user account'],
        'user.updated' => ['label' => 'User updated', 'action' => 'updated a user account'],
        'user.deleted' => ['label' => 'User deleted', 'action' => 'deleted a user account'],
        'user.profile_updated' => ['label' => 'Profile updated', 'action' => 'updated their profile'],
        'user.password_changed' => ['label' => 'Password changed', 'action' => 'changed their password'],

        // ── Applicant authentication events ──────────────────────────────
        'applicant.login' => ['label' => 'Applicant login', 'action' => 'logged in'],
        'applicant.login_failed' => ['label' => 'Failed applicant login', 'action' => 'failed to log in'],
        'applicant.logout' => ['label' => 'Applicant logout', 'action' => 'logged out'],
        'applicant.setup_completed' => ['label' => 'Profile setup completed', 'action' => 'completed profile setup'],
        'applicant.password_reset' => ['label' => 'Password reset', 'action' => 'reset their password'],
        'applicant.registered' => ['label' => 'Applicant registered', 'action' => 'registered an account'],

        // ── Application events ───────────────────────────────────────────
        'application.created' => ['label' => 'Application created', 'action' => 'submitted an application'],
        'application.created_by_staff' => ['label' => 'Application created by staff', 'action' => 'created an application on behalf of applicant'],
        'application.updated' => ['label' => 'Application updated', 'action' => 'updated an application'],
        'application.deleted' => ['label' => 'Application deleted', 'action' => 'deleted an application'],
        'application.accepted' => ['label' => 'Application accepted', 'action' => 'accepted an application'],
        'application.dismissed' => ['label' => 'Application dismissed', 'action' => 'dismissed an application'],
        'application.reopened' => ['label' => 'Application reopened', 'action' => 'reopened an application'],
        'application.bulk_accepted' => ['label' => 'Applications bulk accepted', 'action' => 'bulk-accepted applications'],
        'application.bulk_dismissed' => ['label' => 'Applications bulk dismissed', 'action' => 'bulk-dismissed applications'],
        'application.bulk_reopened' => ['label' => 'Applications bulk reopened', 'action' => 'bulk-reopened applications'],
        'application.setup_email_resent' => ['label' => 'Setup email resent', 'action' => 'resent setup email'],
        'application.status_changed' => ['label' => 'Application status changed', 'action' => 'changed application status'],
        'application.created_by_staff' => ['label' => 'Application created by staff', 'action' => 'created an application on behalf of applicant'],
        'application.portal_updated' => ['label' => 'Application updated by applicant', 'action' => 'updated their application via portal'],

        // ── Application import events ────────────────────────────────────
        'import.applicants_uploaded' => ['label' => 'Applicant import uploaded', 'action' => 'uploaded applicant import file'],
        'import.applicants_confirmed' => ['label' => 'Applicant import confirmed', 'action' => 'confirmed applicant import'],
        'import.scores_uploaded' => ['label' => 'Score import uploaded', 'action' => 'uploaded score import file'],
        'import.scores_confirmed' => ['label' => 'Score import confirmed', 'action' => 'confirmed score import'],
        'import.scores' => ['label' => 'Scores imported', 'action' => 'imported scores'],
        'knowledge.imported' => ['label' => 'Knowledge docs imported', 'action' => 'imported knowledge documents'],

        // ── Exam session events ──────────────────────────────────────────
        'exam_session.created' => ['label' => 'Exam session created', 'action' => 'created an exam session'],
        'exam_session.updated' => ['label' => 'Exam session updated', 'action' => 'updated an exam session'],
        'exam_session.deleted' => ['label' => 'Exam session deleted', 'action' => 'deleted an exam session'],
        'exam_session.published' => ['label' => 'Exam session published', 'action' => 'published an exam session'],
        'exam_session.unpublished' => ['label' => 'Exam session unpublished', 'action' => 'unpublished an exam session'],
        'exam_session.started' => ['label' => 'Exam started', 'action' => 'started an exam session'],
        'exam_session.completed' => ['label' => 'Exam completed', 'action' => 'completed an exam session'],
        'exam_session.cancelled' => ['label' => 'Exam cancelled', 'action' => 'cancelled an exam session'],
        'exam_session.reopened' => ['label' => 'Exam session reopened', 'action' => 'reopened an exam session'],
        'exam_session.applicants_assigned' => ['label' => 'Applicants assigned to exam', 'action' => 'assigned applicants to exam session'],
        'exam_session.applicant_removed' => ['label' => 'Applicant removed from exam', 'action' => 'removed an applicant from exam session'],
        'exam_session.archived' => ['label' => 'Exam archived', 'action' => 'archived an exam'],

        // ── Attendance & submission events ───────────────────────────────
        'attendance.recorded' => ['label' => 'Attendance recorded', 'action' => 'recorded attendance'],
        'attendance.bulk_recorded' => ['label' => 'Bulk attendance recorded', 'action' => 'recorded bulk attendance'],
        'attendance.updated' => ['label' => 'Attendance updated', 'action' => 'updated attendance'],
        'submission.created' => ['label' => 'Answer submitted', 'action' => 'submitted an answer'],
        'submission.bulk_created' => ['label' => 'Bulk submissions recorded', 'action' => 'recorded bulk submissions'],
        'submission.updated' => ['label' => 'Answer updated', 'action' => 'updated an answer'],
        'submission.bulk_recorded' => ['label' => 'Bulk submissions recorded', 'action' => 'recorded submissions in bulk'],

        // ── Proctor session events ─────────────────────────────────────
        'session.proctor_started' => ['label' => 'Session started by proctor', 'action' => 'started a session (proctor)'],
        'session.proctor_closed' => ['label' => 'Session closed by proctor', 'action' => 'closed a session (proctor)'],
        'session.extended' => ['label' => 'Session extended', 'action' => 'extended a session'],

        // ── Grading events ───────────────────────────────────────────────
        'score.entered' => ['label' => 'Score entered', 'action' => 'entered a score'],
        'score.updated' => ['label' => 'Score updated', 'action' => 'updated a score'],
        'score.cleared' => ['label' => 'Scores cleared', 'action' => 'cleared scores'],
        'grading_session.created' => ['label' => 'Grading session created', 'action' => 'created a grading session'],
        'grading_session.finalized' => ['label' => 'Grading finalized', 'action' => 'finalized grading'],

        // ── Direct assessment events ─────────────────────────────────────
        'direct_assessment.created' => ['label' => 'Direct assessment created', 'action' => 'created a direct assessment'],

        // ── Release events ───────────────────────────────────────────────
        'release.updated' => ['label' => 'Release summary updated', 'action' => 'updated release summary'],
        'release.released' => ['label' => 'Result released', 'action' => 'released a result'],
        'release.bulk_released' => ['label' => 'Results bulk released', 'action' => 'bulk-released results'],
        'release.unreleased' => ['label' => 'Result unreleased', 'action' => 'reversed a release'],
        'release.all_released' => ['label' => 'All results released', 'action' => 'released all results'],
        'release.saved' => ['label' => 'Release data saved', 'action' => 'saved release data'],
        'release.bulk_completed' => ['label' => 'Bulk release completed', 'action' => 'completed a bulk release'],
        'release.reverted' => ['label' => 'Release reverted', 'action' => 'reverted a release'],
        'release.all_completed' => ['label' => 'All results released', 'action' => 'released all results'],
        'consultation.released' => ['label' => 'Results released', 'action' => 'released consultation results'],
        'result_sheet.rendered' => ['label' => 'Result sheet rendered', 'action' => 'rendered result sheets'],
        'result_sheet.downloaded_docx' => ['label' => 'Result sheet DOCX downloaded', 'action' => 'downloaded a DOCX result sheet'],

        // ── Setup / reference data events ────────────────────────────────
        'academic_year.created' => ['label' => 'Academic year created', 'action' => 'created an academic year'],
        'academic_year.updated' => ['label' => 'Academic year updated', 'action' => 'updated an academic year'],
        'academic_year.activated' => ['label' => 'Academic year activated', 'action' => 'activated an academic year'],
        'academic_year.deactivated' => ['label' => 'Academic year deactivated', 'action' => 'deactivated an academic year'],

        'course.created' => ['label' => 'Course created', 'action' => 'created a course'],
        'course.updated' => ['label' => 'Course updated', 'action' => 'updated a course'],
        'course.deleted' => ['label' => 'Course deleted', 'action' => 'deleted a course'],
        'course.activated' => ['label' => 'Course activated', 'action' => 'activated a course'],
        'course.deactivated' => ['label' => 'Course deactivated', 'action' => 'deactivated a course'],

        'room.created' => ['label' => 'Room created', 'action' => 'created a room'],
        'room.updated' => ['label' => 'Room updated', 'action' => 'updated a room'],
        'room.deleted' => ['label' => 'Room deleted', 'action' => 'deleted a room'],
        'room.activated' => ['label' => 'Room activated', 'action' => 'activated a room'],
        'room.deactivated' => ['label' => 'Room deactivated', 'action' => 'deactivated a room'],

        'aptitude_area.created' => ['label' => 'Aptitude area created', 'action' => 'created an aptitude area'],
        'aptitude_area.updated' => ['label' => 'Aptitude area updated', 'action' => 'updated an aptitude area'],
        'aptitude_area.deleted' => ['label' => 'Aptitude area deleted', 'action' => 'deleted an aptitude area'],

        'rating_scale.created' => ['label' => 'Rating scale created', 'action' => 'created a rating scale'],
        'rating_scale.updated' => ['label' => 'Rating scale updated', 'action' => 'updated a rating scale'],
        'rating_scale.deleted' => ['label' => 'Rating scale deleted', 'action' => 'deleted a rating scale'],

        'privacy_policy.created' => ['label' => 'Privacy policy created', 'action' => 'created a privacy policy'],
        'privacy_policy.updated' => ['label' => 'Privacy policy updated', 'action' => 'updated a privacy policy'],
        'privacy_policy.deleted' => ['label' => 'Privacy policy deleted', 'action' => 'deleted a privacy policy'],
        'privacy_policy.activated' => ['label' => 'Privacy policy activated', 'action' => 'activated a privacy policy'],
        'privacy_policy.deactivated' => ['label' => 'Privacy policy deactivated', 'action' => 'deactivated a privacy policy'],

        'template.created' => ['label' => 'Template created', 'action' => 'created a template'],
        'template.updated' => ['label' => 'Template updated', 'action' => 'updated a template'],
        'template.deleted' => ['label' => 'Template deleted', 'action' => 'deleted a template'],
        'template.admission_slip_created' => ['label' => 'Admission slip template created', 'action' => 'created an admission slip template'],
        'template.admission_slip_updated' => ['label' => 'Admission slip template updated', 'action' => 'updated an admission slip template'],
        'template.admission_slip_deleted' => ['label' => 'Admission slip template deleted', 'action' => 'deleted an admission slip template'],
        'template.result_sheet_created' => ['label' => 'Result sheet template created', 'action' => 'created a result sheet template'],
        'template.result_sheet_updated' => ['label' => 'Result sheet template updated', 'action' => 'updated a result sheet template'],
        'template.result_sheet_deleted' => ['label' => 'Result sheet template deleted', 'action' => 'deleted a result sheet template'],

        'settings.updated' => ['label' => 'System settings updated', 'action' => 'updated system settings'],

        'institution.updated' => ['label' => 'Institution settings updated', 'action' => 'updated institution settings'],
        'institution.reset' => ['label' => 'Institution settings reset', 'action' => 'reset institution settings to defaults'],

        // ── Knowledge document events ────────────────────────────────────
        'knowledge.created' => ['label' => 'Knowledge doc created', 'action' => 'created a knowledge document'],
        'knowledge.updated' => ['label' => 'Knowledge doc updated', 'action' => 'updated a knowledge document'],
        'knowledge.deleted' => ['label' => 'Knowledge doc deleted', 'action' => 'deleted a knowledge document'],
        'knowledge.imported' => ['label' => 'Knowledge docs imported', 'action' => 'imported knowledge documents'],

        // ── AI companion events ──────────────────────────────────────────
        'ai_companion.persona_updated' => ['label' => 'AI persona updated', 'action' => 'updated AI companion persona'],
        'ai_companion.history_cleared' => ['label' => 'AI history cleared', 'action' => 'cleared AI companion history'],

        // ── Role management events ───────────────────────────────────────
        'role.created' => ['label' => 'Role created', 'action' => 'created a role'],
        'role.updated' => ['label' => 'Role updated', 'action' => 'updated a role'],
        'role.deleted' => ['label' => 'Role deleted', 'action' => 'deleted a role'],
        'role.assigned' => ['label' => 'Role assigned', 'action' => 'assigned a role'],

        // ── Audit log events ─────────────────────────────────────────────
        'audit_log.viewed' => ['label' => 'Audit log viewed', 'action' => 'viewed audit log'],
        'audit_log.exported' => ['label' => 'Audit log exported', 'action' => 'exported audit log'],
    ];

    /**
     * Map of category codes to human-readable labels.
     */
    protected static array $categoryLabels = [
        'auth' => 'Authentication',
        'application' => 'Applications',
        'exam_session' => 'Exam Sessions',
        'grading' => 'Grading',
        'consultation' => 'Release',
        'user_management' => 'User Management',
        'setup' => 'Setup & Configuration',
        'import' => 'Data Import',
        'system' => 'System',
        'other' => 'Other',
    ];

    /**
     * Get human-readable label for an event.
     */
    public static function getEventLabel(string $event): string
    {
        return self::$eventLabels[$event]['label'] ?? $event;
    }

    /**
     * Get human-readable label for a category.
     */
    public static function getCategoryLabel(string $category): string
    {
        return self::$categoryLabels[$category] ?? $category;
    }

    /**
     * Get all events with their labels, sorted by label.
     * For UI dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getEventOptions(): array
    {
        $options = [];
        foreach (self::$eventLabels as $value => $data) {
            $options[] = ['value' => $value, 'label' => $data['label']];
        }
        usort($options, fn ($a, $b) => $a['label'] <=> $b['label']);

        return $options;
    }

    /**
     * Get all categories with their labels.
     * For UI dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getCategoryOptions(): array
    {
        $options = [];
        foreach (self::$categoryLabels as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        usort($options, fn ($a, $b) => $a['label'] <=> $b['label']);

        return $options;
    }

    /**
     * Log an audit event. Insert-only; no update/delete.
     * Auto-generates summary from event if not provided.
     */
    public function log(
        string $event,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $summary = null
    ): void {
        $actorType = null;
        $actorId = null;

        if (auth()->check()) {
            $user = auth()->user();
            $actorType = $user->getMorphClass();
            $actorId = $user->getKey();
        } elseif (auth()->guard('applicant')->check()) {
            $applicant = auth()->guard('applicant')->user();
            $actorType = $applicant->getMorphClass();
            $actorId = $applicant->getKey();
        }

        $request = Request::instance();
        $ipAddress = $request ? $request->ip() : null;
        $userAgent = $request && $request->userAgent() ? $request->userAgent() : null;

        $category = $this->categoryForEvent($event);
        $oldValues = $this->redact($oldValues);
        $newValues = $this->redact($newValues);

        // Auto-generate summary if not provided
        $summary ??= $this->generateSummary($event, $newValues);

        AuditLog::create([
            'auditable_type' => $auditableType ?? '',
            'auditable_id' => $auditableId ?? 0,
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'category' => $category,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate a human-readable summary from event and values.
     */
    protected function generateSummary(string $event, array $newValues): string
    {
        $action = self::$eventLabels[$event]['action'] ?? 'performed an action';
        $actorName = $newValues['name'] ?? $newValues['email'] ?? null;

        if ($actorName) {
            return "User {$action}: {$actorName}";
        }

        return ucfirst($action);
    }

    protected function redact(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, self::$redactKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    protected function categoryForEvent(string $event): string
    {
        $prefix = explode('.', $event)[0] ?? '';
        $map = [
            'user' => 'auth',
            'applicant' => 'auth',
            'application' => 'application',
            'attendance' => 'exam_session',
            'submission' => 'exam_session',
            'session' => 'exam_session',
            'score' => 'grading',
            'grading_session' => 'grading',
            'direct_assessment' => 'grading',
            'consultation' => 'consultation',
            'release' => 'consultation',
            'result_sheet' => 'consultation',
            'import' => 'import',
            'knowledge' => 'system',
            'ai_companion' => 'system',
            'template' => 'setup',
            'privacy_policy' => 'setup',
            'academic_year' => 'setup',
            'course' => 'setup',
            'room' => 'setup',
            'aptitude_area' => 'setup',
            'rating_scale' => 'setup',
            'settings' => 'system',
            'role' => 'user_management',
            'audit_log' => 'system',
            'exam_session' => 'exam_session',
            'institution' => 'setup',
        ];

        return $map[$prefix] ?? 'other';
    }
}
