<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function array_is_list;
use function array_keys;
use function array_map;
use function array_values;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function str_contains;
use function str_starts_with;
use function usort;

/**
 * Diagnostic rehearsal and qualification report service (OpenSpec 11.2 - 11.5).
 *
 * Slices verification runs and scenario manifests into machine outcomes,
 * optional rehearsal decisions, raw gate outcomes, and qualification statuses.
 * Enforces that human reviews never change raw scenario success, generated
 * diagnostic status, or physical printer-device qualification.
 */
final class OmrDiagnosticReportService
{
    public const REPORT_SCHEMA = 'nexiam.omr.diagnostic-report.v1';

    public const REDACTED_SCHEMA = 'nexiam.omr.redacted-diagnostic-report.v1';

    public function __construct(
        private readonly ?OmrVerificationTargetLoader $targets = null,
        private readonly ?OmrPhysicalQualificationDeriver $qualificationDeriver = null,
    ) {}

    /**
     * Build the complete diagnostic and qualification report.
     *
     * @return array<string, mixed>
     */
    public function buildReport(OmrTemplateVerificationRun $run, ?OmrDiagnosticScenarioManifest $manifest = null): array
    {
        $manifest ??= $this->resolveManifest($run);
        $qualification = ($this->qualificationDeriver ?? app(OmrPhysicalQualificationDeriver::class))->derive($run);

        if ($manifest === null) {
            return $this->untestedReport($run, $qualification);
        }

        $captures = $run->captures()->with(['alignedAsset', 'inputAsset', 'overlayAsset'])->get();
        $itemReviews = $this->loadItemReviews($run);
        $scenariosReport = [];

        $generatedPassed = 0;
        $generatedFailed = 0;
        $generatedUntested = 0;

        $handPassed = 0;
        $handFailed = 0;
        $handUntested = 0;

        foreach ($manifest->scenarios as $scenario) {
            $scenarioReport = $this->evaluateScenario($scenario, $captures, $itemReviews);
            $scenariosReport[] = $scenarioReport;

            $lane = (string) $scenario['lane'];
            $status = $scenarioReport['machine_outcome']['status'];

            if ($lane === OmrDiagnosticScenarioManifest::LANE_GENERATED) {
                match ($status) {
                    'pass' => $generatedPassed++,
                    'fail' => $generatedFailed++,
                    default => $generatedUntested++,
                };
            } else {
                match ($status) {
                    'pass' => $handPassed++,
                    'fail' => $handFailed++,
                    default => $handUntested++,
                };
            }
        }

        $coverage = $manifest->coverage();

        $generatedTotal = $generatedPassed + $generatedFailed + $generatedUntested;
        $generatedStatus = $generatedTotal === 0 ? 'untested' : ($generatedFailed === 0 && $generatedUntested === 0 ? 'passed' : ($generatedFailed > 0 ? 'failed' : 'in_progress'));

        $handTotal = $handPassed + $handFailed + $handUntested;
        $handStatus = $handTotal === 0 ? 'untested' : ($handFailed === 0 && $handUntested === 0 ? 'passed' : ($handFailed > 0 ? 'failed' : 'in_progress'));

        return [
            'schema_version' => self::REPORT_SCHEMA,
            'run_id' => (int) $run->id,
            'run_mode' => (string) $run->mode,
            'run_status' => (string) $run->status,
            'manifest_hash' => $manifest->hash(),
            'manifest_version' => $manifest->manifestVersion,
            'plan' => $manifest->plan,
            'lane' => $manifest->lane,
            'target' => [
                'id' => (int) ($run->draft_id ?? $run->template_version_id ?? 0),
                'kind' => $run->draft_id !== null ? 'candidate' : 'compiled_version',
                'hashes' => $run->pinnedTargetHashes(),
            ],
            'provenance' => $manifest->provenance,
            'lane_summaries' => [
                'generated' => [
                    'status' => $generatedStatus,
                    'diagnostic_passed' => $generatedStatus === 'passed',
                    'total_scenarios' => $generatedTotal,
                    'passed_scenarios' => $generatedPassed,
                    'failed_scenarios' => $generatedFailed,
                    'untested_scenarios' => $generatedUntested,
                ],
                'hand_shaded' => [
                    'status' => $handStatus,
                    'diagnostic_passed' => $handStatus === 'passed',
                    'total_scenarios' => $handTotal,
                    'passed_scenarios' => $handPassed,
                    'failed_scenarios' => $handFailed,
                    'untested_scenarios' => $handUntested,
                    'physical_qualification_status' => (string) ($qualification['status'] ?? 'pending'),
                    'physical_qualified' => (bool) ($qualification['qualified'] ?? false),
                    'physical_ready' => (bool) ($qualification['ready'] ?? false),
                ],
            ],
            'qualification_status' => [
                'generated_diagnostic_passed' => $generatedStatus === 'passed',
                'physical_qualification_status' => (string) ($qualification['status'] ?? 'pending'),
                'physical_qualified' => (bool) ($qualification['qualified'] ?? false),
                'physical_ready' => (bool) ($qualification['ready'] ?? false),
                'blockers' => $qualification['blockers'] ?? [],
            ],
            'coverage' => [
                'is_complete' => $coverage->isComplete(),
                'gaps' => $coverage->gaps,
                'required' => $coverage->required,
                'covered' => $coverage->covered,
                'missing' => $coverage->missing,
            ],
            'scenarios' => $scenariosReport,
        ];
    }

    /**
     * Export a redacted manifest/coverage report without file storage paths,
     * candidate PII, or free-text review notes.
     *
     * @return array<string, mixed>
     */
    public function exportRedactedReport(OmrTemplateVerificationRun $run, ?OmrDiagnosticScenarioManifest $manifest = null): array
    {
        $rawReport = $this->buildReport($run, $manifest);
        $redacted = $this->sanitizeForExport($rawReport);
        $redacted['schema_version'] = self::REDACTED_SCHEMA;

        $canonical = $this->canonicalize($redacted);
        $canonicalJson = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $redacted['report_hash'] = hash('sha256', $canonicalJson);

        return $redacted;
    }

    /**
     * Evaluate one manifest scenario against captured evidence and human review.
     *
     * @param  array<string, mixed>  $scenario
     * @param  Collection<int, OmrTemplateVerificationCapture>  $captures
     * @param  array<string, array<string, mixed>>  $itemReviews
     * @return array<string, mixed>
     */
    private function evaluateScenario(
        array $scenario,
        $captures,
        array $itemReviews,
    ): array {
        $scenarioId = (string) $scenario['id'];
        $lane = (string) $scenario['lane'];
        $item = (array) ($scenario['item'] ?? []);
        $expected = (array) ($scenario['expected'] ?? []);
        $expectedClass = (string) ($expected['machine_class'] ?? OmrDiagnosticScenarioManifest::EXPECTED_EXACT);
        $label = (string) ($item['label'] ?? '');
        $pageNo = (int) ($item['page_no'] ?? 1);

        $capture = $this->findMatchingCapture($captures, $scenario);
        $machineOutcome = [
            'status' => 'untested',
            'state' => null,
            'selected_option' => null,
            'observed_options' => [],
            'raw_gate_outcome' => 'untested',
            'issues' => [],
        ];

        if ($capture !== null) {
            $summary = is_array($capture->summary) ? $capture->summary : [];
            $evidenceData = is_array($summary['evidence'] ?? null) ? $summary['evidence'] : [];
            $items = is_array($evidenceData['items'] ?? null) ? $evidenceData['items'] : [];

            $matchedItem = null;
            foreach ($items as $it) {
                if ((string) ($it['native_label'] ?? '') === $label) {
                    $matchedItem = $it;
                    break;
                }
            }

            $detected = is_array($matchedItem['detected'] ?? null) ? $matchedItem['detected'] : [];
            $detectedState = (string) ($detected['state'] ?? 'blank');
            $detectedOption = isset($detected['selected_option']) ? (string) $detected['selected_option'] : null;
            $observedOptions = is_array($detected['observed_options'] ?? null) ? array_values(array_map('strval', $detected['observed_options'])) : ($detectedOption !== null ? [$detectedOption] : []);

            $gateOutcome = (string) ($capture->status === OmrTemplateVerificationCapture::STATUS_PASS ? 'pass' : ($capture->actual === 'typed_rejection' ? 'rejected' : 'fail'));

            // Check if machine output matches expected class
            $isMatch = match ($expectedClass) {
                OmrDiagnosticScenarioManifest::EXPECTED_EXACT => (
                    $detectedState === 'marked'
                    && $detectedOption === (string) ($expected['selected_option'] ?? '')
                    && $gateOutcome === 'pass'
                ),
                OmrDiagnosticScenarioManifest::EXPECTED_REVIEW_REQUIRED => (
                    in_array($detectedState, ['ambiguous', 'unreadable', 'blank'], true)
                    || $gateOutcome === 'pass'
                    || ! empty($summary['issues'] ?? [])
                ),
                OmrDiagnosticScenarioManifest::EXPECTED_CAPTURE_REJECTED => (
                    $gateOutcome === 'rejected'
                    || $capture->status === OmrTemplateVerificationCapture::STATUS_FAIL
                ),
                default => false,
            };

            $machineOutcome = [
                'status' => $isMatch ? 'pass' : 'fail',
                'state' => $detectedState,
                'selected_option' => $detectedOption,
                'observed_options' => $observedOptions,
                'raw_gate_outcome' => $gateOutcome,
                'issues' => is_array($summary['issues'] ?? null) ? $summary['issues'] : [],
            ];
        }

        // Separate human review column: Never changes $machineOutcome['status']!
        $reviewKey = $pageNo.'|'.$label;
        $reviewData = $itemReviews[$reviewKey] ?? null;

        $humanReview = [
            'reviewed' => $reviewData !== null,
            'decision' => $reviewData['decision'] ?? null,
            'reviewed_option' => $reviewData['reviewed_option'] ?? null,
            'reason_code' => $reviewData['reason_code'] ?? null,
            'evidence_hash' => $reviewData['evidence_hash'] ?? null,
            'reviewed_at' => $reviewData['reviewed_at'] ?? null,
        ];

        // Effective production answer (separate projection)
        $effectiveAnswer = null;
        $effectiveState = 'unresolved';
        if ($humanReview['reviewed']) {
            if ($humanReview['decision'] === 'set_option') {
                $effectiveAnswer = $humanReview['reviewed_option'];
                $effectiveState = 'resolved';
            } elseif ($humanReview['decision'] === 'confirm_machine') {
                $effectiveAnswer = $machineOutcome['selected_option'];
                $effectiveState = 'resolved';
            } elseif ($humanReview['decision'] === 'blank') {
                $effectiveAnswer = null;
                $effectiveState = 'resolved';
            }
        } elseif ($machineOutcome['state'] === 'marked' && $machineOutcome['selected_option'] !== null) {
            $effectiveAnswer = $machineOutcome['selected_option'];
            $effectiveState = 'resolved';
        }

        return [
            'scenario_id' => $scenarioId,
            'lane' => $lane,
            'seed' => (string) $scenario['seed'],
            'item' => [
                'page_no' => $pageNo,
                'label' => $label,
            ],
            'mark_style' => (string) ($scenario['mark']['style'] ?? ''),
            'answer_condition' => (string) ($scenario['answer']['condition'] ?? ''),
            'capture_condition' => (string) ($scenario['capture']['condition'] ?? ''),
            'expected_class' => $expectedClass,
            'expected_option' => $expected['selected_option'] ?? null,
            'candidates' => (array) ($expected['candidates'] ?? []),
            'machine_outcome' => $machineOutcome,
            'human_review' => $humanReview,
            'effective_production' => [
                'effective_answer' => $effectiveAnswer,
                'effective_state' => $effectiveState,
            ],
        ];
    }

    /**
     * Find matching capture for a scenario based on lane, page, and variant.
     *
     * @param  Collection<int, OmrTemplateVerificationCapture>  $captures
     * @param  array<string, mixed>  $scenario
     */
    private function findMatchingCapture($captures, array $scenario): ?OmrTemplateVerificationCapture
    {
        $pageNo = (int) ($scenario['item']['page_no'] ?? 1);
        $lane = (string) ($scenario['lane'] ?? OmrDiagnosticScenarioManifest::LANE_GENERATED);
        $captureCondition = (string) ($scenario['capture']['condition'] ?? '');

        return $captures->first(static function (OmrTemplateVerificationCapture $c) use ($pageNo, $lane, $captureCondition): bool {
            if ((int) $c->page_no !== $pageNo) {
                return false;
            }
            if ($lane === OmrDiagnosticScenarioManifest::LANE_HAND_SHADED) {
                return $c->method === OmrTemplateVerificationCapture::METHOD_HAND_SHADED;
            }

            if ($captureCondition === OmrDiagnosticScenarioManifest::CAPTURE_CLEAN_SCANNER) {
                return $c->variant === 'clean' || $c->method === OmrTemplateVerificationCapture::METHOD_PRE_SHADED;
            }

            return $c->variant === $captureCondition || $c->method === OmrTemplateVerificationCapture::METHOD_PRE_SHADED;
        });
    }

    private function loadItemReviews(OmrTemplateVerificationRun $run): array
    {
        $reviews = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('omr_item_review_revisions')) {
                $revisions = DB::table('omr_item_review_revisions')
                    ->join('omr_template_verification_item_evidence', 'omr_item_review_revisions.verification_item_evidence_id', '=', 'omr_template_verification_item_evidence.id')
                    ->join('omr_template_verification_captures', 'omr_template_verification_item_evidence.capture_id', '=', 'omr_template_verification_captures.id')
                    ->where('omr_template_verification_captures.run_id', $run->id)
                    ->orderBy('omr_item_review_revisions.id')
                    ->select([
                        'omr_item_review_revisions.*',
                        'omr_template_verification_item_evidence.item_id',
                        'omr_template_verification_captures.page_no',
                    ])
                    ->get();

                foreach ($revisions as $rev) {
                    $key = ((int) $rev->page_no).'|'.((string) $rev->item_id);
                    $reviews[$key] = [
                        'decision' => (string) $rev->decision,
                        'reviewed_option' => $rev->selected_option !== null ? (string) $rev->selected_option : null,
                        'reason_code' => $rev->reason_code !== null ? (string) $rev->reason_code : null,
                        'evidence_hash' => (string) $rev->reviewed_machine_evidence_hash,
                        'reviewed_at' => (string) $rev->created_at,
                    ];
                }
            }
        } catch (\Throwable) {
            // continue
        }

        try {
            $captures = $run->captures()->pluck('id');
            if ($captures->isNotEmpty()) {
                $itemEvidence = OmrTemplateVerificationItemEvidence::query()
                    ->whereIn('capture_id', $captures)
                    ->get();

                foreach ($itemEvidence as $evidence) {
                    $capture = $evidence->capture;
                    $pageNo = (int) ($capture?->page_no ?? 1);
                    $key = $pageNo.'|'.$evidence->item_id;
                    if (! isset($reviews[$key])) {
                        $revMeta = (array) ($evidence->options['review'] ?? []);
                        if (isset($revMeta['decision'])) {
                            $reviews[$key] = [
                                'decision' => (string) $revMeta['decision'],
                                'reviewed_option' => isset($revMeta['selected_option']) ? (string) $revMeta['selected_option'] : null,
                                'reason_code' => isset($revMeta['reason_code']) ? (string) $revMeta['reason_code'] : null,
                                'evidence_hash' => (string) ($evidence->evidence_hash ?? ''),
                                'reviewed_at' => $evidence->updated_at?->toIso8601String(),
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // continue
        }

        return $reviews;
    }

    private function resolveManifest(OmrTemplateVerificationRun $run): ?OmrDiagnosticScenarioManifest
    {
        $plan = $run->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
            ? OmrDiagnosticScenarioManifest::FULL_DIAGNOSTIC_V1
            : OmrDiagnosticScenarioManifest::QUICK_REHEARSAL_V1;

        $lane = $run->methods()->where('method', OmrTemplateVerificationCapture::METHOD_HAND_SHADED)->exists()
            ? OmrDiagnosticScenarioManifest::LANE_HAND_SHADED
            : OmrDiagnosticScenarioManifest::LANE_GENERATED;

        $reviewManifest = null;
        if ($run->draft_id !== null) {
            $draft = $run->draft ?? OmrTemplateAuthoringDraft::query()->with('assets')->find($run->draft_id);
            $authoring = is_array($draft?->authoring_json) ? $draft->authoring_json : [];
            $canonical = is_array($authoring['canonical'] ?? null) ? $authoring['canonical'] : [];
            $artifactSet = is_array($canonical['artifact_set'] ?? null) ? $canonical['artifact_set'] : [];
            $reviewManifest = $artifactSet['review_manifest'] ?? $canonical['review_manifest'] ?? null;
            if (! is_array($reviewManifest)) {
                $asset = $draft?->assets()->where('kind', OmrTemplateAsset::KIND_REVIEW_MANIFEST)->latest('id')->first();
                if ($asset !== null) {
                    $json = Storage::disk($asset->disk)->get($asset->path);
                    if (is_string($json)) {
                        $reviewManifest = json_decode($json, true);
                    }
                }
            }
        } elseif ($run->template_version_id !== null) {
            $version = $run->templateVersion ?? OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id);
            $manifestData = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
            $reviewManifest = $manifestData['review_manifest'] ?? null;
        }

        if (is_array($reviewManifest) && isset($reviewManifest['fields']) && is_array($reviewManifest['fields'])) {
            try {
                return OmrDiagnosticScenarioManifest::fromReviewManifest(
                    reviewManifest: $reviewManifest,
                    pins: $run->pinnedTargetHashes(),
                    plan: $plan,
                    lane: $lane,
                );
            } catch (\Throwable) {
                // continue to loader
            }
        }

        try {
            $loader = $this->targets ?? app(OmrVerificationTargetLoader::class);
            if ($run->mode === OmrTemplateVerificationRun::MODE_DIGITAL) {
                $loaded = $loader->load($run);
                $target = $loaded['target'];
            } else {
                $methodName = $run->methods()->where('method', OmrTemplateVerificationCapture::METHOD_HAND_SHADED)->exists()
                    ? OmrTemplateVerificationCapture::METHOD_HAND_SHADED
                    : OmrTemplateVerificationCapture::METHOD_PRE_SHADED;
                $loaded = $loader->loadPhysical($run, $methodName);
                $target = $loaded['target'];
            }

            return OmrDiagnosticScenarioManifest::fromReviewManifest(
                reviewManifest: $target->reviewManifest(),
                pins: $run->pinnedTargetHashes(),
                plan: $plan,
                lane: $lane,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Untested fallback report when target geometry has no scenario manifest.
     *
     * @param  array<string, mixed>  $qualification
     * @return array<string, mixed>
     */
    private function untestedReport(OmrTemplateVerificationRun $run, array $qualification): array
    {
        return [
            'schema_version' => self::REPORT_SCHEMA,
            'run_id' => (int) $run->id,
            'run_mode' => (string) $run->mode,
            'run_status' => (string) $run->status,
            'manifest_hash' => null,
            'manifest_version' => 'v1',
            'plan' => 'untested',
            'lane' => 'untested',
            'target' => [
                'id' => (int) ($run->draft_id ?? $run->template_version_id ?? 0),
                'kind' => $run->draft_id !== null ? 'candidate' : 'compiled_version',
                'hashes' => $run->pinnedTargetHashes(),
            ],
            'provenance' => [],
            'lane_summaries' => [
                'generated' => [
                    'status' => 'untested',
                    'diagnostic_passed' => false,
                    'total_scenarios' => 0,
                    'passed_scenarios' => 0,
                    'failed_scenarios' => 0,
                    'untested_scenarios' => 0,
                ],
                'hand_shaded' => [
                    'status' => 'untested',
                    'diagnostic_passed' => false,
                    'total_scenarios' => 0,
                    'passed_scenarios' => 0,
                    'failed_scenarios' => 0,
                    'untested_scenarios' => 0,
                    'physical_qualification_status' => (string) ($qualification['status'] ?? 'pending'),
                    'physical_qualified' => (bool) ($qualification['qualified'] ?? false),
                    'physical_ready' => (bool) ($qualification['ready'] ?? false),
                ],
            ],
            'qualification_status' => [
                'generated_diagnostic_passed' => false,
                'physical_qualification_status' => (string) ($qualification['status'] ?? 'pending'),
                'physical_qualified' => (bool) ($qualification['qualified'] ?? false),
                'physical_ready' => (bool) ($qualification['ready'] ?? false),
                'blockers' => $qualification['blockers'] ?? [],
            ],
            'coverage' => [
                'is_complete' => false,
                'gaps' => ['manifest_unavailable'],
                'required' => [],
                'covered' => [],
                'missing' => [],
            ],
            'scenarios' => [],
        ];
    }

    /**
     * Recursively strip paths, PII, and raw notes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeForExport(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $keyStr = (string) $key;

            if (in_array($keyStr, ['path', 'disk', 'file_path', 'source_path', 'output_path', 'note', 'notes', 'raw_note'], true)) {
                continue;
            }

            if (str_contains($keyStr, 'applicant') || str_contains($keyStr, 'student') || str_contains($keyStr, 'birthdate')) {
                continue;
            }

            if (is_array($value)) {
                $result[$keyStr] = $this->sanitizeForExport($value);
            } elseif (is_string($value)) {
                if (str_starts_with($value, '/') || str_contains($value, '/tmp/') || str_contains($value, '/var/')) {
                    $result[$keyStr] = '[redacted_path]';
                } else {
                    $result[$keyStr] = $value;
                }
            } else {
                $result[$keyStr] = $value;
            }
        }

        return $result;
    }

    /**
     * Canonicalize keys and list ordering for reproducible hashing.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_values(array_map([$this, 'canonicalize'], $value));
        }

        $keys = array_keys($value);
        usort($keys, static fn (int|string $left, int|string $right): int => strcmp((string) $left, (string) $right));
        $sorted = [];
        foreach ($keys as $key) {
            $sorted[(string) $key] = $this->canonicalize($value[$key]);
        }

        return $sorted;
    }
}
