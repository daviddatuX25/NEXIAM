<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Assessment\Omr\Review\OmrEffectiveAnswerDecision;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_values;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function strtolower;
use function trim;

/**
 * Projects the answer vector that is authoritative for a diagnostic capture.
 *
 * The projector is deliberately answer-key blind. It only combines immutable
 * machine evidence with the latest review revision whose evidence hash still
 * matches that evidence. Expected answers are introduced by the comparison
 * action after this projection has completed.
 */
final class OmrDiagnosticEffectiveAnswerProjector
{
    public const SCHEMA_VERSION = 'nexiam.omr.diagnostic-effective-answers.v1';

    /**
     * Derive, persist, and return effective answers for one diagnostic capture.
     *
     * @return array<string, mixed>
     */
    public function project(OmrDiagnosticCapture $capture): array
    {
        return DB::transaction(function () use ($capture): array {
            $run = OmrDiagnosticRun::query()
                ->lockForUpdate()
                ->findOrFail((int) $capture->diagnostic_run_id);
            $lockedCapture = OmrDiagnosticCapture::query()
                ->lockForUpdate()
                ->findOrFail((int) $capture->getKey());

            if ((string) $run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
                throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
            }

            $evidence = OmrDiagnosticItemEvidence::query()
                ->forCapture((int) $lockedCapture->getKey())
                ->stableOrder()
                ->get();

            $evidenceIds = $evidence
                ->map(static fn (OmrDiagnosticItemEvidence $item): int => (int) $item->getKey())
                ->values()
                ->all();
            $reviews = $evidenceIds === []
                ? collect()
                : OmrDiagnosticReviewRevision::query()
                    ->whereIn('diagnostic_item_evidence_id', $evidenceIds)
                    ->orderBy('diagnostic_item_evidence_id')
                    ->orderByDesc('revision')
                    ->lockForUpdate()
                    ->get()
                    ->unique('diagnostic_item_evidence_id')
                    ->keyBy('diagnostic_item_evidence_id');

            $items = [];
            $answers = [];
            $unresolvedKeys = [];
            $staleKeys = [];
            $reviewedCount = 0;
            $machineCount = 0;

            foreach ($evidence as $item) {
                $itemKey = trim((string) $item->item_key);
                if ($itemKey === '') {
                    throw new RuntimeException('omr_diagnostic_item_key_invalid');
                }

                $machine = $this->machineProposal($item);
                $review = $reviews->get((int) $item->getKey());
                $reviewArray = $review instanceof OmrDiagnosticReviewRevision
                    ? $review->toSafeArray()
                    : null;
                $machineHash = strtolower(trim((string) $item->evidence_hash));
                $reviewHash = $review instanceof OmrDiagnosticReviewRevision
                    ? strtolower(trim((string) $review->reviewed_evidence_hash))
                    : null;
                $stale = $review !== null
                    && ($reviewHash === null || ! hash_equals($machineHash, $reviewHash));
                $reviewDecision = $review instanceof OmrDiagnosticReviewRevision
                    ? ($review->decision instanceof OmrReviewDecision
                        ? $review->decision
                        : OmrReviewDecision::tryFrom((string) $review->decision))
                    : null;
                $reviewOption = $review instanceof OmrDiagnosticReviewRevision
                    ? $this->nullableString($review->final_option)
                    : null;
                $effective = OmrEffectiveAnswerDecision::resolve(
                    $machine['state'],
                    $machine['selected_option'],
                    $reviewDecision,
                    $reviewOption,
                    $stale,
                );
                $effectiveState = $effective['state'];
                $effectiveOption = $effective['option'];
                $effectiveSource = $effective['source'];

                if ($effective['reviewed']) {
                    $reviewedCount++;
                }
                if ($effective['machine']) {
                    $machineCount++;
                }

                if ($stale) {
                    $staleKeys[] = $itemKey;
                }
                if ($effectiveState === 'unresolved') {
                    $unresolvedKeys[] = $itemKey;
                }

                $answers[$itemKey] = [
                    'state' => $effectiveState,
                    'option' => $effectiveOption,
                ];
                $items[] = [
                    'item_key' => $itemKey,
                    'page_no' => (int) $item->page_no,
                    'area_code' => $item->area_code !== null ? (string) $item->area_code : null,
                    'question_no' => $item->question_no !== null ? (int) $item->question_no : null,
                    'option_code' => $item->option_code !== null ? (string) $item->option_code : null,
                    'option_evidence' => $this->safeValue($item->option_evidence_json),
                    'machine_proposal' => $machine['safe_proposal'],
                    'machine_state' => $machine['state'],
                    'machine_selected_option' => $machine['selected_option'],
                    'machine_confidence' => $machine['confidence'],
                    'evidence_hash' => $machineHash,
                    'evidence_version' => $this->evidenceVersion($item),
                    'latest_review' => $reviewArray,
                    'review_stale' => $stale,
                    'effective_state' => $effectiveState,
                    'effective_option' => $effectiveOption,
                    'effective_source' => $effectiveSource,
                ];
            }

            $unresolvedKeys = array_values(array_unique($unresolvedKeys));
            $staleKeys = array_values(array_unique($staleKeys));
            $projection = [
                'schema_version' => self::SCHEMA_VERSION,
                'run_id' => (int) $run->getKey(),
                'capture_id' => (int) $lockedCapture->getKey(),
                'capture_version' => (int) $lockedCapture->version,
                'run_revision' => (int) $run->revision,
                'items' => $items,
                'answers' => $answers,
                'summary' => [
                    'total' => count($items),
                    'resolved' => count($items) - count($unresolvedKeys),
                    'unresolved' => count($unresolvedKeys),
                    'stale' => count($staleKeys),
                    'reviewed' => $reviewedCount,
                    'machine' => $machineCount,
                ],
                'is_complete' => $unresolvedKeys === [] && $staleKeys === [],
                'has_unresolved' => $unresolvedKeys !== [],
                'has_stale' => $staleKeys !== [],
                'unresolved_item_keys' => $unresolvedKeys,
                'stale_item_keys' => $staleKeys,
            ];

            // Effective answers are a derived capture projection. The input,
            // machine evidence, and review rows remain immutable/append-only.
            $lockedCapture->forceFill([
                'effective_answers_json' => $projection,
            ])->saveQuietly();

            return $projection;
        });
    }

    /**
     * @return array{state:string, selected_option:string|null, confidence:float|null, safe_proposal:array<string|int, mixed>}
     */
    private function machineProposal(OmrDiagnosticItemEvidence $item): array
    {
        $raw = is_array($item->machine_proposal_json) ? $item->machine_proposal_json : [];
        $detected = is_array($raw['detected'] ?? null) ? $raw['detected'] : [];
        $state = strtolower(trim((string) ($raw['state'] ?? $raw['status'] ?? $detected['state'] ?? '')));
        $selected = $raw['selected_option'] ?? ($detected['selected_option'] ?? null);
        $selected = $this->nullableString(
            is_string($selected) || is_numeric($selected)
                ? (string) $selected
                : ($item->option_code !== null ? (string) $item->option_code : null),
        );
        if ($state === '' && $selected !== null) {
            $state = 'marked';
        }
        if (! in_array($state, ['marked', 'blank', 'ambiguous', 'unreadable', 'unresolved'], true)) {
            $state = $selected !== null ? 'marked' : 'unresolved';
        }
        if ($state !== 'marked') {
            $selected = null;
        }

        $confidence = is_numeric($raw['confidence'] ?? ($detected['confidence'] ?? null))
            ? (float) ($raw['confidence'] ?? $detected['confidence'])
            : ($item->machine_confidence !== null ? (float) $item->machine_confidence : null);

        return [
            'state' => $state,
            'selected_option' => $selected,
            'confidence' => $confidence,
            'safe_proposal' => $this->safeValue($raw),
        ];
    }

    private function evidenceVersion(OmrDiagnosticItemEvidence $item): int
    {
        $value = trim((string) $item->evidence_version);
        if (preg_match('/^v?(\d+)$/i', $value, $matches) !== 1 || (int) $matches[1] < 1) {
            throw new RuntimeException('omr_diagnostic_evidence_version_invalid');
        }

        return (int) $matches[1];
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function safeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $child) {
            if (is_string($key)) {
                $normalized = strtolower($key);
                if (str_contains($normalized, 'expected')
                    || str_contains($normalized, 'correct')
                    || str_contains($normalized, 'answer_key')
                    || str_contains($normalized, 'qualification')
                    || $normalized === 'score'
                    || str_contains($normalized, 'projected_score')) {
                    continue;
                }
            }
            $safe[$key] = $this->safeValue($child);
        }

        return $safe;
    }
}
