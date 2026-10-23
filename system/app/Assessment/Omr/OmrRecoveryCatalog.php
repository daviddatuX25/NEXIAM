<?php

namespace App\Assessment\Omr;

/**
 * One safe operator-facing catalog for resolver and capture failures.
 */
final class OmrRecoveryCatalog
{
    /**
     * @return array{
     *     code: string,
     *     title: string,
     *     explanation: string,
     *     blocking: bool,
     *     owner: string,
     *     primary_action: string,
     *     secondary_action: ?string
     * }
     */
    public function for(string $code): array
    {
        $entries = [
            'session_unresolved' => ['Session not found', 'No scannable submitted session matches this Applicant Number.', 'Scoring staff', 'retry_scan', null],
            'session_ambiguous' => ['Choose the session', 'More than one eligible submitted session matches this Applicant Number.', 'Scoring staff', 'select_session', 'retry_scan'],
            'session_missing' => ['Session unavailable', 'The scanner session is no longer available.', 'Scoring staff', 'return_to_grading', null],
            'applicant_unresolved' => ['Applicant Number not resolved', 'Retry the scan or match an eligible assignment from this session.', 'Scoring staff', 'retry_scan', 'match_applicant'],
            'applicant_conflict' => ['Applicant Number conflict', 'More than one eligible assignment matches; staff must choose from the session roster.', 'Scoring staff', 'match_applicant', 'retry_scan'],
            'applicant_missing' => ['Applicant record unavailable', 'The eligible assignment no longer has an Applicant record.', 'Decision support staff', 'open_support', null],
            'applicant_duplicate' => ['Possible duplicate scan', 'Another capture in this batch already uses this applicant.', 'Scoring staff', 'compare_duplicates', null],
            'applicant_duplicate_prior_batch' => ['Possible duplicate from an earlier batch', 'Another capture in this grading session already uses this applicant in a different batch. Scoring support must verify the prior disposition before continuing.', 'Scoring support', 'open_support', null],
            'submission_missing' => ['Submission not recorded', 'Record the submission in Exam Monitoring, then return to this scan.', 'Test Administrator', 'open_monitoring', null],
            'seat_missing' => ['Frozen seat unavailable', 'The submission does not have a valid frozen session-seat allocation.', 'Exam setup staff', 'open_exam_setup', null],
            'seat_snapshot_missing' => ['Frozen session snapshot unavailable', 'The submitted seat is not part of this exam session snapshot.', 'Exam setup staff', 'open_exam_setup', null],
            'key_missing' => ['Answer key unavailable', 'The frozen booklet Set has no immutable answer key.', 'Assessment setup staff', 'open_sheet_builder', null],
            'observed_set_mismatch' => ['Observed Set conflict', 'The visible Set differs from the submission-frozen seat; scoring remains blocked.', 'Assessment setup staff', 'review_material_assignment', null],
            'qr_missing' => ['QR not readable', 'Rescan first; if the code is still unreadable, use the printed display code.', 'Scoring staff', 'retry_scan', 'enter_display_code'],
            'qr_disabled_capture_requires_pending_set' => [
                'QR-disabled capture is not available on this route',
                'This paper has no signed QR provenance. The signed-QR upload path cannot claim it; only a supported route-bound pending capture set may process it.',
                'Scoring support',
                'open_support',
                null,
            ],
            'pairing_missing' => ['Applicant Number missing from a page', 'Capture or match the same non-null Applicant Number on every expected page before material resolution.', 'Scoring staff', 'retry_scan', 'match_applicant'],
            'pairing_conflict' => ['Applicant Numbers differ across pages', 'The expected pages do not agree on one Applicant Number; compare the pages and match the correct examinee before continuing.', 'Scoring staff', 'match_applicant', 'retry_scan'],
            'identity_review_required' => ['Write-in Applicant Number confirmation required', 'Inspect the write-in Applicant Number region on every page and select the matching eligible examinee before adoption.', 'Scoring staff', 'match_applicant', 'retry_scan'],
            'qr_disabled_claim_missing' => ['QR-disabled page claim missing', 'Every ordered page must have one claim from the selected completed production capture set.', 'Scoring support', 'retry_scan', 'open_support'],
            'qr_disabled_claim_mixed' => ['QR-disabled page claims are mixed', 'All page claims must belong to the same production capture set and printed issuance, without signed QR provenance.', 'Scoring support', 'quarantine_scan', 'open_support'],
            'qr_disabled_claim_stale' => ['QR-disabled page evidence is stale', 'A page claim no longer matches the selected pending-page evidence hash, page, or captured sheet page.', 'Scoring support', 'retry_scan', 'open_support'],
            'qr_disabled_issuance_invalid' => ['QR-disabled issuance unavailable', 'The printed QR-disabled issuance is void, incomplete, or does not match the pinned template, scope, and copy policy.', 'Assessment setup staff', 'open_sheet_builder', 'open_support'],
            'qr_disabled_stock_invalid' => ['QR-disabled stock provenance invalid', 'The production capture set does not match its pinned batch, template, institution, cycle, adoption, or ready lifecycle.', 'Scoring support', 'open_support', 'open_sheet_builder'],
            'qr_disabled_stock_exhausted' => ['QR-disabled stock exhausted', 'This unique-instance issuance has reached its authorized copy count. Issue additional stock before accepting another capture.', 'Assessment setup staff', 'open_sheet_builder', 'open_support'],
            'supplier_stock_invalid' => ['Supplier stock allocation unavailable', 'The supplier stock allocation or lot is void, mismatched, or unavailable for this batch.', 'Assessment setup staff', 'open_sheet_builder', 'open_support'],
            'supplier_stock_exhausted' => ['Supplier stock allocation exhausted', 'This supplier stock allocation has no remaining physical sets. Allocate additional supplier stock before adopting another capture.', 'Assessment setup staff', 'open_sheet_builder', 'open_support'],
            'omr_capture_set_already_adopted' => ['Capture set already adopted', 'This pending capture set is already attached to another sheet and cannot be reused.', 'Scoring support', 'open_support', 'retry_scan'],
            'qr_reader_unavailable' => ['QR reader unavailable', 'The QR reader is unavailable. Retry this same capture or use the printed display code.', 'Scoring staff', 'retry_scan', 'enter_display_code'],
            'qr_reader_response_invalid' => ['QR reader response invalid', 'The QR reader returned an invalid response. Retry this same capture or use the printed display code.', 'Scoring staff', 'retry_scan', 'enter_display_code'],
            'multi_page_capture' => ['Multi-page capture required', 'Capture the complete page set from the selected Exam Monitoring session.', 'Scoring staff', 'retry_scan', null],
            'analysis_failed' => ['Scan analysis failed', 'The capture was preserved. Retry the same image or send it for manual review.', 'Scoring staff', 'retry_scan', 'match_applicant'],
            'analysis_evidence_invalid' => ['Scan evidence unavailable', 'The capture was preserved but its machine evidence could not be stored safely.', 'Scoring support', 'retry_scan', null],
            'qr_invalid' => ['QR signature invalid', 'This sheet cannot be bypassed with a display code; quarantine it and rescan.', 'Scoring staff', 'quarantine_scan', 'retry_scan'],
            'qr_instance_missing' => ['Answer Sheet not registered', 'The signed Answer Sheet instance is not registered.', 'Assessment setup staff', 'quarantine_scan', null],
            'qr_provenance_conflict' => ['Answer Sheet provenance conflict', 'The signed QR conflicts with the registered Answer Sheet instance.', 'Scoring staff', 'quarantine_scan', null],
            'qr_scope_mismatch' => ['Answer Sheet scope mismatch', 'The issued Answer Sheet belongs to a different institution or academic cycle than the selected session.', 'Assessment setup staff', 'quarantine_scan', null],
            'qr_duplicate' => ['Answer Sheet page already captured', 'This issued page is already attached to another capture.', 'Scoring staff', 'compare_duplicates', null],
            'qr_duplicate_page' => ['Answer Sheet page already captured', 'This issued page is already attached to another capture.', 'Scoring staff', 'compare_duplicates', null],
            'qr_lifecycle_invalid' => ['Answer Sheet no longer valid', 'This Answer Sheet was voided or superseded.', 'Assessment setup staff', 'quarantine_scan', null],
            'qr_page_invalid' => ['Answer Sheet page mismatch', 'The signed page does not match the issued Answer Sheet page set.', 'Scoring staff', 'retry_scan', null],
            'qr_page_void' => ['Answer Sheet page no longer valid', 'This issued Answer Sheet page was voided and cannot be scored.', 'Assessment setup staff', 'quarantine_scan', null],
            'page_missing_from_set' => ['Answer Sheet page unavailable', 'The issued Answer Sheet does not contain the captured page.', 'Assessment setup staff', 'retry_scan', 'open_sheet_builder'],
            'mixed_page_provenance' => ['Mixed Answer Sheet pages', 'Every captured page must belong to the same issued Answer Sheet instance.', 'Scoring staff', 'quarantine_scan', 'retry_scan'],
            'template_mismatch' => ['Template mismatch', 'The Answer Sheet template does not match the active batch.', 'Assessment setup staff', 'open_sheet_builder', null],
        ];

        [$title, $explanation, $owner, $primary, $secondary] = $entries[$code]
            ?? ['Review required', 'This scan needs staff review before scoring can continue.', 'Scoring support', 'open_support', null];

        return [
            'code' => $code,
            'title' => $title,
            'explanation' => $explanation,
            'blocking' => true,
            'owner' => $owner,
            'primary_action' => $primary,
            'secondary_action' => $secondary,
        ];
    }
}
