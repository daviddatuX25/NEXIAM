<?php

namespace App\Admission\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;

/**
 * Registrar attempt detail — single-record authority for P-REG-02.
 */
final class ShowAdmissionAttemptQuery
{
    /**
     * @return array<string, mixed>
     */
    public function handle(AdmissionAttempt $attempt, ?User $staffActor = null): array
    {
        $attempt->loadMissing([
            'identity:id,first_name,middle_name,last_name,suffix,birthdate,sex,email,phone',
            'academicCycle:id,code,name',
            'gradeValues:id,admission_attempt_id,field_code,value',
            'privacyPolicy:id,title',
        ]);
        $consent = $this->consentProjection($attempt);

        $documentChecklist = app(AttemptRequirementChecklistQuery::class)->get($attempt, $staffActor);
        $requestHistory = AdmissionDocumentCorrectionRequest::applicantProjectionFor($attempt)['requests'];

        $identity = $attempt->identity;
        $name = trim(implode(' ', array_filter([
            $identity?->first_name,
            $identity?->middle_name,
            $identity?->last_name,
            $identity?->suffix,
        ])));

        return [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'version' => $attempt->version,
            'applicant_name' => $name !== '' ? $name : 'Unknown',
            'identity' => [
                'first_name' => $identity?->first_name,
                'middle_name' => $identity?->middle_name,
                'last_name' => $identity?->last_name,
                'suffix' => $identity?->suffix,
                'birthdate' => $identity?->birthdate?->toDateString(),
                'sex' => $identity?->sex,
                'email' => $identity?->email,
                'phone' => $identity?->phone,
            ],
            'contact_email' => $attempt->contact_email ?? $identity?->email,
            'contact_phone' => $attempt->contact_phone ?? $identity?->phone,
            'applicant_type' => $attempt->applicant_type,
            'last_school_enrolled' => $attempt->last_school_enrolled,
            'strand' => $attempt->strand,
            'program_preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'preferred_program_code' => $attempt->program_preference_1_code
                ?? $attempt->preferred_program_code,
            'address' => [
                'line' => $attempt->address_line,
                'city' => $attempt->city,
                'province' => $attempt->province,
                'zip_code' => $attempt->zip_code,
            ],
            'dismissal_reason' => $attempt->dismissal_reason,
            'cycle' => [
                'code' => $attempt->academicCycle?->code,
                'name' => $attempt->academicCycle?->name,
            ],
            'consent' => $consent,
            'document_checklist' => [
                ...$documentChecklist,
                'flags' => [
                    'documents_view' => $documentChecklist['documents_view'] ?? false,
                    'documents_initialize' => $documentChecklist['documents_initialize'] ?? false,
                    'documents_verify' => $documentChecklist['documents_verify'] ?? false,
                    'documents_reconcile' => $documentChecklist['documents_reconcile'] ?? false,
                    'documents_request' => $documentChecklist['documents_request'] ?? false,
                ],
                'request_history' => $requestHistory,
                'expected_version' => (int) $attempt->version,
            ],
            'is_clearance_confirmed' => AttemptProgramFacts::isClearanceConfirmed($attempt),
            'has_active_legacy_stamp' => AttemptProgramFacts::activeStamp($attempt) !== null,
            'submitted_at' => $attempt->created_at?->toIso8601String(),
            'accepted_at' => $attempt->accepted_at?->toIso8601String(),
            'dismissed_at' => $attempt->dismissed_at?->toIso8601String(),
            'archived_at' => $attempt->archived_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{source: string, policy_id: ?int, legacy_policy_id: ?int, public_policy_id: ?int, revision_id: ?int, revision: ?int, digest: ?string, title: ?string, revision_known: bool}|null
     */
    private function consentProjection(AdmissionAttempt $attempt): ?array
    {
        $receipt = $attempt->payload['consent'] ?? null;
        if ($attempt->public_policy_id !== null
            && is_array($receipt)
            && ($receipt['source'] ?? null) === 'public_policy'
            && (int) ($receipt['policy_id'] ?? 0) === (int) $attempt->public_policy_id) {
            $revisionId = filter_var($receipt['revision_id'] ?? null, FILTER_VALIDATE_INT);
            $revisionNumber = filter_var($receipt['revision'] ?? null, FILTER_VALIDATE_INT);
            $digest = $receipt['digest'] ?? null;
            if ($revisionId === false || $revisionId < 1 || $revisionNumber === false || $revisionNumber < 1
                || ! is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
                return $this->unknownRecordedConsent($attempt);
            }

            $revision = PublicPolicyRevision::query()
                ->whereKey($revisionId)
                ->where('public_policy_id', $attempt->public_policy_id)
                ->where('institution_id', $attempt->institution_id)
                ->where('state', 'published')
                ->first();
            if ($revision === null || (int) $revision->revision !== $revisionNumber
                || ! hash_equals($revision->contentDigest(), $digest)) {
                return $this->unknownRecordedConsent($attempt);
            }

            return [
                'source' => 'public_policy',
                'policy_id' => (int) $attempt->public_policy_id,
                'legacy_policy_id' => null,
                'public_policy_id' => (int) $attempt->public_policy_id,
                'revision_id' => (int) $revision->getKey(),
                'revision' => (int) $revision->revision,
                'digest' => $digest,
                'title' => $revision->title,
                'revision_known' => true,
            ];
        }

        return $this->unknownRecordedConsent($attempt);
    }

    /**
     * @return array{source: string, policy_id: ?int, legacy_policy_id: ?int, public_policy_id: ?int, revision_id: null, revision: null, digest: null, title: ?string, revision_known: false}|null
     */
    private function unknownRecordedConsent(AdmissionAttempt $attempt): ?array
    {
        if ($attempt->privacy_policy_id !== null && $attempt->public_policy_id !== null) {
            return [
                'source' => 'unverified',
                'policy_id' => null,
                'legacy_policy_id' => (int) $attempt->privacy_policy_id,
                'public_policy_id' => (int) $attempt->public_policy_id,
                'revision_id' => null,
                'revision' => null,
                'digest' => null,
                'title' => null,
                'revision_known' => false,
            ];
        }

        if ($attempt->privacy_policy_id !== null) {
            return [
                'source' => 'legacy_privacy_policy',
                'policy_id' => (int) $attempt->privacy_policy_id,
                'legacy_policy_id' => (int) $attempt->privacy_policy_id,
                'public_policy_id' => null,
                'revision_id' => null,
                'revision' => null,
                'digest' => null,
                'title' => $attempt->privacyPolicy?->title,
                'revision_known' => false,
            ];
        }

        if ($attempt->public_policy_id !== null) {
            return [
                'source' => 'public_policy',
                'policy_id' => (int) $attempt->public_policy_id,
                'legacy_policy_id' => null,
                'public_policy_id' => (int) $attempt->public_policy_id,
                'revision_id' => null,
                'revision' => null,
                'digest' => null,
                'title' => null,
                'revision_known' => false,
            ];
        }

        return null;
    }
}
