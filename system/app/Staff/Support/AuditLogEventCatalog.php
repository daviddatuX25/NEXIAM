<?php

namespace App\Staff\Support;

use Illuminate\Support\Str;

final class AuditLogEventCatalog
{
    /**
     * @var array<string, array{label: string, icon: string, color: string}>
     */
    public const DOMAINS = [
        'admissions' => [
            'label' => 'Admissions',
            'icon' => 'user-check',
            'color' => 'emerald',
        ],
        'assessment' => [
            'label' => 'Assessment & OMR',
            'icon' => 'clipboard-check',
            'color' => 'indigo',
        ],
        'consultation' => [
            'label' => 'Consultation & Inquiry',
            'icon' => 'messages-square',
            'color' => 'purple',
        ],
        'setup' => [
            'label' => 'Institutional Setup',
            'icon' => 'sliders',
            'color' => 'amber',
        ],
        'security' => [
            'label' => 'Security & Access',
            'icon' => 'shield',
            'color' => 'rose',
        ],
        'system' => [
            'label' => 'System & Operations',
            'icon' => 'cpu',
            'color' => 'slate',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const KNOWN_TITLES = [
        'registrar.eligibility_lookup' => 'Registrar Eligibility Lookup',
        'admission.attempt_accepted' => 'Admission Attempt Accepted',
        'admission.attempt_corrected' => 'Admission Attempt Corrected',
        'admission.applicant_amended_pending' => 'Applicant Self-Amended Pending Application',
        'admission.accepted_amendment_authorized' => 'Authorized Amendment on Accepted Application',
        'admission.clearance.confirmed' => 'Admission Clearance Confirmed',
        'admission.clearance.revoked' => 'Admission Clearance Revoked',
        'admission.attempt_imported' => 'Admission Attempt Imported',
        'admission.attempt_dismissed' => 'Admission Attempt Dismissed',
        'admission.attempt_archived' => 'Admission Attempt Archived',
        'admission.applicant_login' => 'Applicant Authenticated',
        'admission.access_challenge_issued' => 'Applicant Access Challenge Issued',
        'admission.requirement_set_initialized' => 'Requirement Checklist Initialized',
        'admission.office_inquiry.opened' => 'Office Inquiry Opened',
        'admission.office_inquiry.message_sent' => 'Office Inquiry Message Sent',
        'admission.office_inquiry.read_marked' => 'Office Inquiry Marked as Read',
        'office_inquiry.list_viewed' => 'Office Inquiries Viewed',
        'office_inquiry.message_viewed' => 'Inquiry Message Viewed',
        'program.selection_acknowledged' => 'Program Selection Acknowledged',
        'program.selection_declined' => 'Program Selection Declined',
        'program.selection_cancelled' => 'Program Selection Cancelled',
        'program.follow_up_opened' => 'Program Follow-up Opened',
        'thread.opened' => 'Support Thread Opened',
        'thread.message_sent' => 'Support Message Sent',
        'audit.logs_exported' => 'Audit Logs Exported',
        'examination.session_published' => 'Exam Session Published',
        'examination.session_cancelled' => 'Exam Session Cancelled',
        'examination.attendance_marked' => 'Exam Attendance Recorded',
        'omr.answer_sheet_bundle_exported' => 'OMR Answer Sheet Bundle Exported',
        'omr.capture_processed' => 'OMR Sheet Capture Processed',
        'exam.slot_switched' => 'Exam Schedule Changed by Applicant',
        'consultation.slot_switched' => 'Release Appointment Changed by Applicant',
    ];

    /**
     * Determine domain category for an event type.
     */
    public static function resolveDomain(string $eventType): string
    {
        $type = strtolower($eventType);

        if (str_starts_with($type, 'enrollment.') || $type === 'registrar.eligibility_lookup') {
            return 'admissions';
        }

        if (
            str_starts_with($type, 'admission.')
            || str_starts_with($type, 'admissions.')
            || str_starts_with($type, 'application.')
            || str_starts_with($type, 'clearance.')
            || str_starts_with($type, 'ingestion.')
            || str_starts_with($type, 'program.selection_')
            || str_starts_with($type, 'program.follow_up_')
        ) {
            // Note: office_inquiry and messaging events under admission are consultation
            if (str_contains($type, 'office_inquiry') || str_contains($type, 'thread') || str_contains($type, 'discussion')) {
                return 'consultation';
            }
            if (str_contains($type, 'applicant_login') || str_contains($type, 'challenge')) {
                return 'security';
            }

            return 'admissions';
        }

        if (
            str_starts_with($type, 'exam')
            || str_starts_with($type, 'assessment.')
            || str_starts_with($type, 'scoring.')
            || str_starts_with($type, 'omr.')
            || str_starts_with($type, 'direct_assessment.')
            || str_starts_with($type, 'grading.')
        ) {
            return 'assessment';
        }

        if (
            str_starts_with($type, 'consultation.')
            || str_starts_with($type, 'office_inquiry.')
            || str_starts_with($type, 'thread.')
            || str_starts_with($type, 'governance_discussion.')
            || str_starts_with($type, 'counseling.')
        ) {
            return 'consultation';
        }

        if (
            str_starts_with($type, 'setup.')
            || str_starts_with($type, 'cycles.')
            || str_starts_with($type, 'rooms.')
            || str_starts_with($type, 'programs.')
            || str_starts_with($type, 'quota.')
            || str_starts_with($type, 'policy.')
            || str_starts_with($type, 'notice.')
            || str_starts_with($type, 'template.')
            || str_starts_with($type, 'registrar.quota_')
        ) {
            return 'setup';
        }

        if (
            str_starts_with($type, 'auth.')
            || str_starts_with($type, 'security.')
            || str_starts_with($type, 'staff.login')
            || str_starts_with($type, 'staff.onboard')
            || str_starts_with($type, 'staff.presence')
            || str_starts_with($type, 'applicant.login')
            || str_starts_with($type, 'two_factor.')
            || str_starts_with($type, 'challenge.')
        ) {
            return 'security';
        }

        return 'system';
    }

    /**
     * @param  list<string>  $eventTypes
     * @return list<string>
     */
    public static function visibleToOffice(array $eventTypes, ?string $office): array
    {
        if ($office === 'admin') {
            return $eventTypes;
        }

        $prefixes = match ($office) {
            'registrar' => ['registrar.', 'admission.', 'admissions.', 'application.', 'clearance.', 'ingestion.', 'enrollment.'],
            'guidance' => ['guidance.', 'exam.', 'examination.', 'assessment.', 'scoring.', 'grading.', 'omr.', 'release.', 'consultation.', 'direct_assessment.', 'staff.ta_'],
            default => [],
        };

        $staffAuthEvents = ['auth.staff_login', 'auth.staff_logout', 'auth.staff_remembered_login'];

        return array_values(array_filter($eventTypes, static function (string $type) use ($prefixes, $office, $staffAuthEvents): bool {
            if ($office === 'registrar' && $type === 'setup.application_suggestions_saved') {
                return true;
            }
            if (in_array($type, $staffAuthEvents, true)) {
                return true;
            }
            foreach ($prefixes as $prefix) {
                if (str_starts_with($type, $prefix)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Human readable display title for event type.
     */
    public static function resolveTitle(string $eventType): string
    {
        if (isset(self::KNOWN_TITLES[$eventType])) {
            return self::KNOWN_TITLES[$eventType];
        }

        $clean = str_replace(['.', '_', '-'], ' ', $eventType);

        return Str::headline($clean);
    }

    /**
     * Synthesize a concise human-readable summary from payload attributes.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function synthesizeSummary(string $eventType, array $payload): string
    {
        $parts = [];

        if (! empty($payload['reference_code'])) {
            $parts[] = 'Ref: '.$payload['reference_code'];
        } elseif (! empty($payload['attempt_id'])) {
            $parts[] = 'Attempt #'.$payload['attempt_id'];
        }

        if (! empty($payload['inquiry_id'])) {
            $parts[] = 'Inquiry #'.$payload['inquiry_id'];
        }

        if (! empty($payload['receipt_number'])) {
            $parts[] = 'Receipt: '.$payload['receipt_number'];
        }

        if (! empty($payload['office'])) {
            $parts[] = 'Office: '.ucfirst((string) $payload['office']);
        }

        if (! empty($payload['fields']) && is_array($payload['fields'])) {
            $count = count($payload['fields']);
            $names = array_slice($payload['fields'], 0, 2);
            $fieldList = implode(', ', array_map(fn ($f) => str_replace('_', ' ', (string) $f), $names));
            $parts[] = $count > 2 ? "Fields: {$fieldList} (+".($count - 2).' more)' : "Fields: {$fieldList}";
        }

        if (! empty($payload['reason'])) {
            $parts[] = 'Reason: '.Str::limit((string) $payload['reason'], 40);
        }

        if (! empty($payload['status'])) {
            $parts[] = 'Status: '.$payload['status'];
        }

        if ($parts === []) {
            if (! empty($payload['action'])) {
                $parts[] = (string) $payload['action'];
            } elseif (! empty($payload['event'])) {
                $parts[] = (string) $payload['event'];
            } else {
                $parts[] = self::resolveTitle($eventType);
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Extract structured change diffs from event payload.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{field: string, label: string, old_value: mixed, new_value: mixed, kind: 'changed'|'added'|'removed'}>
     */
    public static function extractDiff(array $payload): array
    {
        $diff = [];

        // 1. Identity before/after diff (from identity corrections/amendments)
        if (isset($payload['before_identity'], $payload['after_identity']) && is_array($payload['before_identity']) && is_array($payload['after_identity'])) {
            $before = $payload['before_identity'];
            $after = $payload['after_identity'];
            $allKeys = array_unique([...array_keys($before), ...array_keys($after)]);

            foreach ($allKeys as $key) {
                $hasBefore = array_key_exists($key, $before);
                $hasAfter = array_key_exists($key, $after);
                $valBefore = $hasBefore ? $before[$key] : null;
                $valAfter = $hasAfter ? $after[$key] : null;

                if ($valBefore !== $valAfter) {
                    $kind = ! $hasBefore ? 'added' : (! $hasAfter ? 'removed' : 'changed');
                    $diff[] = [
                        'field' => (string) $key,
                        'label' => Str::headline(str_replace('_', ' ', (string) $key)),
                        'old_value' => $valBefore,
                        'new_value' => $valAfter,
                        'kind' => $kind,
                    ];
                }
            }
        }

        // 2. Generic before/after or old/new diff
        if ($diff === [] && isset($payload['before'], $payload['after']) && is_array($payload['before']) && is_array($payload['after'])) {
            $before = $payload['before'];
            $after = $payload['after'];
            $allKeys = array_unique([...array_keys($before), ...array_keys($after)]);

            foreach ($allKeys as $key) {
                $valBefore = $before[$key] ?? null;
                $valAfter = $after[$key] ?? null;
                if ($valBefore !== $valAfter) {
                    $diff[] = [
                        'field' => (string) $key,
                        'label' => Str::headline(str_replace('_', ' ', (string) $key)),
                        'old_value' => $valBefore,
                        'new_value' => $valAfter,
                        'kind' => ! isset($before[$key]) ? 'added' : (! isset($after[$key]) ? 'removed' : 'changed'),
                    ];
                }
            }
        }

        // 3. Status transition diff (e.g. prior_status -> status)
        if (isset($payload['prior_status']) && (isset($payload['status']) || isset($payload['new_status']))) {
            $newStatus = $payload['new_status'] ?? $payload['status'];
            $diff[] = [
                'field' => 'status',
                'label' => 'Status Transition',
                'old_value' => $payload['prior_status'],
                'new_value' => $newStatus,
                'kind' => 'changed',
            ];
        }

        // 4. Fields list diff without explicit before/after values
        if ($diff === [] && ! empty($payload['fields']) && is_array($payload['fields'])) {
            foreach ($payload['fields'] as $field) {
                if (is_string($field)) {
                    $diff[] = [
                        'field' => $field,
                        'label' => Str::headline(str_replace('_', ' ', $field)),
                        'old_value' => 'Prior value not snapshotted',
                        'new_value' => 'Modified in record',
                        'kind' => 'changed',
                    ];
                }
            }
        }

        return $diff;
    }
}
