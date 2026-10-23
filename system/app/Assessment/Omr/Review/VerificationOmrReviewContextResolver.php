<?php

namespace App\Assessment\Omr\Review;

use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Resolves a selected verification evidence row into a review command
 * context. Expected answers and qualification vectors are deliberately not
 * part of the returned machine envelope.
 */
final class VerificationOmrReviewContextResolver
{
    public function resolve(OmrTemplateVerificationItemEvidence|int $item): OmrReviewContext
    {
        $evidence = $item instanceof OmrTemplateVerificationItemEvidence
            ? $item
            : OmrTemplateVerificationItemEvidence::query()->find($item);

        if (! $evidence instanceof OmrTemplateVerificationItemEvidence) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationItemEvidence::class, [(string) $item]);
        }

        $evidence->loadMissing('capture.run.draft', 'capture.run.templateVersion');
        $capture = $evidence->capture;
        if (! $capture instanceof OmrTemplateVerificationCapture) {
            throw new RuntimeException('omr_review_verification_capture_missing');
        }

        return $this->resolveLocked($capture, $evidence);
    }

    public function resolveByIds(int $captureId, int $itemId): OmrReviewContext
    {
        $capture = OmrTemplateVerificationCapture::query()->find($captureId);
        $item = OmrTemplateVerificationItemEvidence::query()
            ->whereKey($itemId)
            ->where('capture_id', $captureId)
            ->first();

        if (! $capture instanceof OmrTemplateVerificationCapture || ! $item instanceof OmrTemplateVerificationItemEvidence) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationItemEvidence::class, [(string) $itemId]);
        }

        $capture->loadMissing('run.draft', 'run.templateVersion');

        return $this->resolveLocked($capture, $item);
    }

    public function resolveLocked(
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationItemEvidence $item,
    ): OmrReviewContext {
        if ((int) $item->capture_id !== (int) $capture->getKey()) {
            throw new RuntimeException('omr_review_verification_item_parent_mismatch');
        }

        $itemKey = trim((string) $item->item_id);
        if ($itemKey === '') {
            throw new RuntimeException('omr_review_item_key_missing');
        }

        $optionSet = $this->optionCodes($item->options);
        if ($optionSet === []) {
            throw new RuntimeException('omr_review_option_set_missing');
        }

        $eligibility = $this->eligibility($capture);
        if (! $eligibility['reviewable']) {
            throw new RuntimeException('omr_review_not_reviewable');
        }
        $machine = $this->machineEnvelope($capture, $item, $optionSet);
        $this->assertCardinality($machine, $optionSet);
        $reviewable = true;

        $evidenceHash = trim((string) $item->evidence_hash);
        if (preg_match('/^[a-fA-F0-9]{64}$/', $evidenceHash) !== 1) {
            throw new RuntimeException('omr_review_evidence_hash_missing');
        }
        $evidenceHash = strtolower($evidenceHash);

        $latest = OmrItemReviewRevision::query()
            ->forVerificationItem((int) $item->getKey())
            ->where('item_key', $itemKey)
            ->orderByDesc('revision')
            ->first();

        $run = $capture->run;
        $scope = [
            'office' => 'guidance',
            'verification_capture_id' => (int) $capture->getKey(),
            'verification_item_evidence_id' => (int) $item->getKey(),
        ];
        if ($run !== null) {
            $scope['verification_run_id'] = (int) $run->getKey();
            if ($run->draft_id !== null) {
                $scope['authoring_draft_id'] = (int) $run->draft_id;
            }
            if ($run->template_version_id !== null) {
                $scope['template_version_id'] = (int) $run->template_version_id;
            }
        }

        return new OmrReviewContext(
            type: OmrReviewContext::TYPE_VERIFICATION,
            parentId: (int) $capture->getKey(),
            itemKey: $itemKey,
            parentVersion: (int) ($capture->lock_version ?? 0),
            itemRevision: (int) ($latest?->revision ?? 0),
            optionSet: $optionSet,
            evidenceHash: $evidenceHash,
            machineState: $machine['state'],
            machineSelectedOption: $machine['selected_option'],
            observedOptions: $machine['observed_options'],
            lifecycle: $reviewable ? 'reviewable' : 'closed',
            reviewable: $reviewable,
            resourceScope: $scope,
            parent: $capture,
            verificationItemEvidenceId: (int) $item->getKey(),
            machineIssueCodes: $machine['issue_codes'],
            machineEnvelope: $machine,
        );
    }

    /**
     * The same physical-page eligibility policy is consumed by evidence reads
     * and fresh write resolution.
     *
     * @return array{reviewable:bool,reasons:list<array{code:string,label:string}>}
     */
    public function eligibility(OmrTemplateVerificationCapture $capture): array
    {
        $deny = static fn (string $code, string $label): array => [
            'reviewable' => false,
            'reasons' => [['code' => $code, 'label' => $label]],
        ];
        $method = OmrTemplateVerificationMethodPage::query()
            ->where('method_id', $capture->method_id)
            ->where('page_no', $capture->page_no)
            ->with('method')
            ->first();
        if ($method === null || $method->method === null
            || (int) $method->method->run_id !== (int) $capture->run_id
            || (string) $method->method->method !== (string) $capture->method
            || (int) $method->method_id !== (int) $capture->method_id) {
            return $deny('verification_page_binding_invalid', 'Capture is not bound to the current verification page.');
        }

        $methodRow = $method->method;
        $run = $capture->run;
        $challenge = $capture->challenge;
        $bindingMatches = $run !== null
            && $challenge !== null
            && (int) $methodRow->run_id === (int) $run->getKey()
            && (int) $methodRow->challenge_id === (int) $capture->challenge_id
            && (int) $challenge->run_id === (int) $run->getKey()
            && (string) ($run->draft_id !== null
                ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
                : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION) === (string) $capture->target_kind
            && (int) ($run->draft_id ?? $run->template_version_id) === (int) $capture->target_id
            && (string) $run->artifact_hash === (string) $capture->target_hash
            && (string) $challenge->method === (string) $capture->method
            && (string) $methodRow->target_kind === (string) $capture->target_kind
            && (int) $methodRow->target_id === (int) $capture->target_id
            && (string) $methodRow->target_version === (string) $capture->target_version
            && (string) $methodRow->target_hash === (string) $capture->target_hash
            && (string) $methodRow->plan_version === (string) $capture->plan_version
            && (string) $methodRow->plan_hash === (string) $capture->plan_hash
            && (string) $methodRow->expected_vector_hash === (string) $capture->expected_vector_hash
            && (string) $challenge->target_kind === (string) $capture->target_kind
            && (int) $challenge->target_id === (int) $capture->target_id
            && (string) $challenge->target_version === (string) $capture->target_version
            && (string) $challenge->target_hash === (string) $capture->target_hash
            && (string) $challenge->plan_version === (string) $capture->plan_version
            && (string) $challenge->plan_hash === (string) $capture->plan_hash
            && (string) $challenge->expected_vector_hash === (string) $capture->expected_vector_hash;
        if (! $bindingMatches) {
            return $deny('verification_capture_binding_stale', 'Capture binding is stale or has been superseded by a retake.');
        }

        $latest = OmrTemplateVerificationCapture::query()
            ->where('method_id', $capture->method_id)
            ->where('page_no', $capture->page_no)
            ->whereNull('discarded_at')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->first(['id', 'attempt_state']);
        if ($latest === null || $latest->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
            return $deny('verification_capture_not_reviewable', 'An active capture attempt prevents item review.');
        }
        $selected = (int) ($method->selected_attempt_id ?? 0) === (int) $capture->getKey();
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $verification = is_array($summary['verification_result'] ?? null) ? $summary['verification_result'] : [];
        $selectedPass = $capture->actual === 'pass'
            || ($capture->actual === 'answer_vector_mismatch'
                && $capture->failure_code === null
                && ($verification['gates_pass'] ?? null) === true);
        if ($selected
            && $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_PASSED
            && $capture->status === OmrTemplateVerificationCapture::STATUS_PASS
            && $selectedPass
            && $method->page_state === OmrTemplateVerificationMethodPage::STATE_PASSED
            && $capture->discarded_at === null
            && (int) $latest->getKey() === (int) $capture->getKey()) {
            return ['reviewable' => true, 'reasons' => []];
        }
        $gateValidFailure = $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED
            && $capture->status === OmrTemplateVerificationCapture::STATUS_FAIL
            && $capture->actual === 'answer_vector_mismatch'
            && $capture->failure_code === 'capture_needs_review'
            && $capture->vector_match === false
            && ($verification['gates_pass'] ?? null) === true
            && (int) ($summary['needs_review_count'] ?? $verification['needs_review_count'] ?? $summary['review_count'] ?? $verification['review_count'] ?? 0) > 0
            && $method->page_state === OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW
            && $capture->discarded_at === null
            && ! $selected;
        if (! $gateValidFailure) {
            return $deny('verification_capture_not_reviewable', 'Only a selected passing capture or a gate-valid answer mismatch can be reviewed.');
        }

        if ((int) $latest->getKey() !== (int) $capture->getKey()) {
            return $deny('verification_capture_binding_stale', 'Capture binding is stale or has been superseded by a retake.');
        }

        return [
            'reviewable' => true,
            'reasons' => [['code' => 'answer_vector_mismatch', 'label' => 'Answer vector differs; gate-valid evidence requires human review.']],
        ];
    }

    /**
     * @return list<string>
     */
    private function optionCodes(mixed $raw): array
    {
        $codes = [];
        foreach ((array) $raw as $entry) {
            $code = is_array($entry) ? ($entry['code'] ?? null) : $entry;
            if (! is_string($code) && ! is_numeric($code)) {
                continue;
            }
            $code = trim((string) $code);
            if ($code !== '' && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $machine
     * @param  list<string>  $optionSet
     */
    private function assertCardinality(array $machine, array $optionSet): void
    {
        $observed = $machine['observed_options'];
        foreach ($observed as $option) {
            if (! in_array($option, $optionSet, true)) {
                throw new RuntimeException('omr_review_machine_option_invalid');
            }
        }

        $state = $machine['state'];
        $selected = $machine['selected_option'];
        if ($state === 'marked' && (count($observed) !== 1 || $selected === null || $selected !== $observed[0])) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'blank' && ($observed !== [] || $selected !== null)) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'ambiguous' && (count($observed) < 2 || $selected !== null)) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'unreadable' && $selected !== null) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
    }

    /**
     * @param  list<string>  $optionSet
     * @return array{state:string,selected_option:string|null,observed_options:list<string>,option_observations:list<array<string,mixed>>,confidence:float|null,issue_codes:list<string>}
     */
    private function machineEnvelope(
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationItemEvidence $item,
        array $optionSet,
    ): array {
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $evidence = is_array($summary['evidence'] ?? null) ? $summary['evidence'] : [];
        $match = null;
        foreach ((array) ($evidence['items'] ?? []) as $candidate) {
            if (! is_array($candidate) || (string) ($candidate['native_label'] ?? '') !== (string) $item->item_id) {
                continue;
            }
            if ($match !== null) {
                throw new RuntimeException('omr_review_machine_evidence_ambiguous');
            }
            $candidateOptions = $this->optionCodes($candidate['options'] ?? []);
            $detected = is_array($candidate['detected'] ?? null) ? $candidate['detected'] : null;
            if ((int) ($candidate['page_no'] ?? 0) !== (int) $capture->page_no
                || strtoupper((string) ($candidate['area_code'] ?? '')) !== strtoupper((string) $item->area_key)
                || (int) ($candidate['question_no'] ?? 0) !== (int) $item->question_no
                || $candidateOptions !== $optionSet
                || $detected === null
                || ! array_key_exists('observed_options', $detected)) {
                throw new RuntimeException('omr_review_machine_evidence_invalid');
            }
            $match = ['detected' => $detected];
        }
        if ($match === null) {
            throw new RuntimeException('omr_review_machine_evidence_missing');
        }

        $detected = $match['detected'];
        $state = strtolower(trim((string) ($detected['state'] ?? '')));
        $selected = $this->scalarCode($detected['selected_option'] ?? null);
        $observed = $this->optionCodes($detected['observed_options']);

        return [
            'state' => $state,
            'selected_option' => $selected,
            'observed_options' => $observed,
            'option_observations' => is_array($detected['option_observations'] ?? null)
                ? array_values($detected['option_observations'])
                : [],
            'confidence' => is_numeric($detected['confidence'] ?? null) ? (float) $detected['confidence'] : null,
            'issue_codes' => is_array($detected['issue_codes'] ?? null)
                ? array_values(array_unique(array_filter(array_map('strval', $detected['issue_codes']))))
                : [],
        ];
    }

    private function scalarCode(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
