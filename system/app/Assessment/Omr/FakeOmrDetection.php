<?php

namespace App\Assessment\Omr;

use Throwable;

use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Deterministic synthetic detector used only for the non-production demo. It
 * receives no roster or answer key and emits observation-only v4 output with
 * candidate-complete item evidence. Document-bound requests still require a real rectifier.
 */
final class FakeOmrDetection implements OmrDetectionPort, OmrLayoutVerificationPort
{
    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        $dimensions = (array) ($request->nativeTemplate['pageDimensions'] ?? []);

        $seed = hash('sha256', $request->inputHash);
        $score = 0.90 + (hexdec(substr($seed, 0, 8)) / 4294967295.0) * 0.09;

        return new OmrLayoutVerificationResult(
            outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
            alignedImage: [
                'mime' => 'image/png',
                'width' => (int) ($dimensions[0] ?? $request->imageWidth ?? 1),
                'height' => (int) ($dimensions[1] ?? $request->imageHeight ?? 1),
            ],
            markerMatches: [
                'quarter_1' => $score,
                'quarter_2' => $score,
                'quarter_3' => $score,
                'quarter_4' => $score,
            ],
            gates: [
                'marker_count' => 'pass',
                'registration' => 'pass',
                'dimensions' => 'pass',
                'shifts' => 'pass',
                'unsafe_blocks' => [],
            ],
        );
    }

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $started = microtime(true);

        try {
            return OmrDetectionResult::succeeded(
                $this->succeed($request),
                new OmrDetectionTimings($this->elapsedMs($started), 3.2),
            );
        } catch (Throwable) {
            return OmrDetectionResult::failed(
                OmrDetectionOutcome::TerminalFailure,
                'fake.unexpected_error',
                'The synthetic detector failed unexpectedly.',
                new OmrDetectionTimings($this->elapsedMs($started)),
            );
        }
    }

    private function succeed(OmrDetectionRequest $request): OmrDetectionSuccess
    {
        $applicantNumber = config('omr.fake.applicant_number');
        $hasIdentitySignal = is_string($applicantNumber) && $applicantNumber !== '';
        $identityFields = [];
        $issues = [];

        if ($hasIdentitySignal) {
            $identityFields[] = new OmrIdentityField(
                code: OmrIdentityField::CODE_APPLICANT_NUMBER,
                region: $this->identityRegion($request),
                confidence: 1.0,
                value: $applicantNumber,
            );
        } else {
            $issues[] = new OmrDetectionIssue(
                OmrDetectionIssue::CODE_IDENTITY_UNRESOLVED,
                OmrDetectionIssue::SEVERITY_WARNING,
            );
        }

        $birthdate = config('omr.fake.birthdate');
        $birthdateProposal = null;
        if (isset($request->identityRegions[OmrIdentityField::CODE_BIRTHDATE])) {
            // Per-page map wins so tests can fabricate cross-page identity
            // conflicts deterministically regardless of job timing.
            $byPage = (array) config('omr.fake.birthdate_by_page', []);
            $birthdateProposal = array_key_exists($request->pageNo, $byPage)
                ? (string) $byPage[$request->pageNo]
                : (is_string($birthdate) && $birthdate !== '' ? $birthdate : '01012010');
            $identityFields[] = new OmrIdentityField(
                code: OmrIdentityField::CODE_BIRTHDATE,
                region: $request->identityRegions[OmrIdentityField::CODE_BIRTHDATE],
                confidence: 1.0,
                value: $birthdateProposal,
            );
        }

        $items = [];
        $confidenceSum = 0.0;

        foreach ($request->items as $geometry) {
            [$state, $selectedOption, $observedOptions, $optionObservations, $confidence, $itemIssues, $issueCodes] = $this->roll($request, $geometry);
            $items[] = new OmrDetectedItem(
                position: $geometry->position,
                areaCode: $geometry->areaCode,
                questionNo: $geometry->questionNo,
                selectedOption: $selectedOption,
                state: $state,
                confidence: $confidence,
                region: $geometry->region,
                observedOptions: $observedOptions,
                optionObservations: $optionObservations,
                issueCodes: $issueCodes,
            );
            $confidenceSum += $confidence;
            $issues = [...$issues, ...$itemIssues];
        }

        $sheetConfidence = $items === [] ? 0.0 : $confidenceSum / count($items);

        $alignedImage = null;
        if ((bool) config('omr.fake.with_aligned_image', false)) {
            $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
            $alignedImage = [
                'mime' => 'image/png',
                'width' => 1,
                'height' => 1,
                'bytes' => $bytes,
                'hash' => hash('sha256', $bytes),
            ];
        }

        return new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: (string) config('omr.provider.version', '1'),
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: $hasIdentitySignal ? $applicantNumber : null,
            applicantNumberConfidence: $hasIdentitySignal ? 1.0 : 0.0,
            identityFields: $identityFields,
            birthdateProposal: $birthdateProposal,
            items: $items,
            issues: $issues,
            sheetConfidence: round($sheetConfidence, 4),
            responseEnvelopeVersion: $request->envelopeVersion,
            alignedImage: $alignedImage,
            runId: $request->runId,
            correlationId: $request->correlationId,
            bookletHash: $request->bookletHash,
            nativeBundleHash: $request->nativeBundleHash,
            manifestHash: $request->manifestHash,
            patchIdentity: $request->patchIdentity,
            calibrationStatus: 'synthetic',
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
            registrationMode: $request->registrationMode->value,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            expectedPageNo: $request->pageNo,
            sourceImage: $request->sourceImage->toArray(),
        );
    }

    /**
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function roll(OmrDetectionRequest $request, OmrItemGeometry $geometry): array
    {
        $configuredCase = $this->resolveConfiguredEvidenceCase($request, $geometry);
        if ($configuredCase !== null) {
            return $this->buildConfiguredEvidenceCase($geometry, $configuredCase);
        }

        $seed = hash('sha256', $request->inputHash.'|'.$geometry->areaCode.'|'.$geometry->questionNo);
        $roll = $this->fraction($seed, 0);
        $fixtureCorrect = $this->fixtureCorrectCount($request, $geometry->areaCode);
        $optionCodes = array_map(
            static fn (OmrOptionGeometry $option): string => $option->code,
            $geometry->options,
        );
        $selectedOption = $optionCodes[0] ?? null;

        if ($fixtureCorrect !== null) {
            $isMarked = $selectedOption !== null && $geometry->questionNo <= $fixtureCorrect;
            $state = $isMarked ? OmrDetectedItem::STATE_MARKED : OmrDetectedItem::STATE_BLANK;
            $observedOptions = $isMarked ? [$selectedOption] : [];

            return [
                $state,
                $isMarked ? $selectedOption : null,
                $observedOptions,
                $this->optionObservations($geometry, $observedOptions, $state),
                $isMarked ? 0.98 : 0.78,
                [],
                [],
            ];
        }

        if ($roll < 0.70 && $selectedOption !== null) {
            $observedOptions = [$selectedOption];

            return [
                OmrDetectedItem::STATE_MARKED,
                $selectedOption,
                $observedOptions,
                $this->optionObservations($geometry, $observedOptions, OmrDetectedItem::STATE_MARKED),
                round(0.90 + $this->fraction($seed, 1) * 0.09, 4),
                [],
                [],
            ];
        }

        if ($roll < 0.82) {
            return [
                OmrDetectedItem::STATE_BLANK,
                null,
                [],
                $this->optionObservations($geometry, [], OmrDetectedItem::STATE_BLANK),
                0.72,
                [],
                [],
            ];
        }

        if ($roll < 0.91 && count($optionCodes) >= 2) {
            $observedOptions = array_slice($optionCodes, 0, 2);
            $issue = new OmrDetectionIssue(
                OmrDetectionIssue::CODE_AMBIGUOUS_MARK,
                OmrDetectionIssue::SEVERITY_REQUIRED,
                $geometry->areaCode,
                $geometry->questionNo,
            );

            return [
                OmrDetectedItem::STATE_AMBIGUOUS,
                null,
                $observedOptions,
                $this->optionObservations($geometry, $observedOptions, OmrDetectedItem::STATE_AMBIGUOUS),
                0.31,
                [$issue],
                [OmrDetectionIssue::CODE_AMBIGUOUS_MARK],
            ];
        }

        $observedOptions = array_slice($optionCodes, 0, min(2, count($optionCodes)));
        $issue = new OmrDetectionIssue(
            OmrDetectionIssue::CODE_UNREADABLE,
            OmrDetectionIssue::SEVERITY_REQUIRED,
            $geometry->areaCode,
            $geometry->questionNo,
        );

        return [
            OmrDetectedItem::STATE_UNREADABLE,
            null,
            $observedOptions,
            $this->optionObservations($geometry, $observedOptions, OmrDetectedItem::STATE_UNREADABLE),
            0.22,
            [$issue],
            [OmrDetectionIssue::CODE_UNREADABLE],
        ];
    }

    /**
     * @param  string|array<string, mixed>  $configuredCase
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildConfiguredEvidenceCase(OmrItemGeometry $geometry, mixed $configuredCase): array
    {
        $caseSpec = is_array($configuredCase) ? $configuredCase : ['case' => (string) $configuredCase];
        $caseName = OmrSyntheticEvidenceCase::normalizeCaseName(
            (string) ($caseSpec['case'] ?? $caseSpec['type'] ?? $caseSpec['name'] ?? OmrSyntheticEvidenceCase::CASE_DEFINITE)
        );

        $optionCodes = array_map(
            static fn (OmrOptionGeometry $option): string => $option->code,
            $geometry->options,
        );

        return match ($caseName) {
            OmrSyntheticEvidenceCase::CASE_BLANK => $this->buildBlankCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_AMBIGUOUS => $this->buildAmbiguousCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_UNREADABLE => $this->buildUnreadableCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_ERASED => $this->buildErasedCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_CROSSED => $this->buildCrossedCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_PARTIAL => $this->buildPartialCase($geometry, $optionCodes, $caseSpec),
            OmrSyntheticEvidenceCase::CASE_OUT_OF_REGION => $this->buildOutOfRegionCase($geometry, $optionCodes, $caseSpec),
            default => $this->buildDefiniteCase($geometry, $optionCodes, $caseSpec),
        };
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildDefiniteCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $selected = (string) ($caseSpec['selected_option'] ?? $caseSpec['option'] ?? ($optionCodes[0] ?? 'A'));
        $observedOptions = [$selected];
        $meanSelected = (float) ($caseSpec['mean'] ?? 32.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            $isMarked = $option->code === $selected;
            $observations[] = new OmrOptionObservation(
                code: $option->code,
                mean: $isMarked ? $meanSelected : ($unmarkedBase + $index),
                classification: $isMarked ? 'marked' : 'unmarked',
            );
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? []);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_WARNING);

        return [
            OmrDetectedItem::STATE_MARKED,
            $selected,
            $observedOptions,
            $observations,
            (float) ($caseSpec['confidence'] ?? 0.98),
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildBlankCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $unmarkedBase = (float) ($caseSpec['mean'] ?? 245.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            $observations[] = new OmrOptionObservation(
                code: $option->code,
                mean: $unmarkedBase + $index,
                classification: 'unmarked',
            );
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? []);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_WARNING);

        return [
            OmrDetectedItem::STATE_BLANK,
            null,
            [],
            $observations,
            (float) ($caseSpec['confidence'] ?? 0.72),
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildAmbiguousCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $candidates = (array) ($caseSpec['observed_options'] ?? $caseSpec['candidates'] ?? []);
        if ($candidates === [] && count($optionCodes) >= 2) {
            $candidates = array_slice($optionCodes, 0, 2);
        } elseif ($candidates === [] && count($optionCodes) === 1) {
            $candidates = $optionCodes;
        }

        $candidateBase = (float) ($caseSpec['mean'] ?? 34.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            $isCandidate = in_array($option->code, $candidates, true);
            $observations[] = new OmrOptionObservation(
                code: $option->code,
                mean: $isCandidate ? ($candidateBase + $index) : ($unmarkedBase + $index),
                classification: $isCandidate ? 'ambiguous_candidate' : 'unmarked',
            );
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? [OmrDetectionIssue::CODE_AMBIGUOUS_MARK]);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_REQUIRED);

        return [
            OmrDetectedItem::STATE_AMBIGUOUS,
            null,
            $candidates,
            $observations,
            (float) ($caseSpec['confidence'] ?? 0.31),
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildUnreadableCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $observedOptions = (array) ($caseSpec['observed_options'] ?? []);
        $meanBase = (float) ($caseSpec['mean'] ?? 120.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            $isCandidate = in_array($option->code, $observedOptions, true);
            $observations[] = new OmrOptionObservation(
                code: $option->code,
                mean: $meanBase + $index,
                classification: $isCandidate ? 'unreadable_candidate' : 'unreadable',
            );
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? [OmrDetectionIssue::CODE_UNREADABLE]);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_REQUIRED);

        return [
            OmrDetectedItem::STATE_UNREADABLE,
            null,
            $observedOptions,
            $observations,
            (float) ($caseSpec['confidence'] ?? 0.18),
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildErasedCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $erasedOption = (string) ($caseSpec['erased_option'] ?? ($optionCodes[0] ?? 'A'));
        $explicitState = isset($caseSpec['state']) ? (string) $caseSpec['state'] : null;

        if ($explicitState === OmrDetectedItem::STATE_BLANK || count($optionCodes) < 2) {
            $state = OmrDetectedItem::STATE_BLANK;
            $selected = null;
            $observedOptions = [];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.70);
        } else {
            $state = OmrDetectedItem::STATE_MARKED;
            $defaultMarked = ($erasedOption === ($optionCodes[0] ?? 'A')) && isset($optionCodes[1])
                ? $optionCodes[1]
                : ($optionCodes[0] ?? 'A');
            $selected = (string) ($caseSpec['selected_option'] ?? $caseSpec['marked_option'] ?? $defaultMarked);
            $observedOptions = [$selected];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.88);
        }

        $erasedMean = (float) ($caseSpec['erased_mean'] ?? 155.0);
        $markedMean = (float) ($caseSpec['marked_mean'] ?? 32.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            if ($option->code === $erasedOption) {
                $classification = 'erased';
                $mean = $erasedMean;
            } elseif ($state === OmrDetectedItem::STATE_MARKED && $option->code === $selected) {
                $classification = 'marked';
                $mean = $markedMean;
            } else {
                $classification = 'unmarked';
                $mean = $unmarkedBase + $index;
            }
            $observations[] = new OmrOptionObservation($option->code, $mean, $classification);
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? []);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_WARNING);

        return [
            $state,
            $selected,
            $observedOptions,
            $observations,
            $confidence,
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildCrossedCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $crossedOption = (string) ($caseSpec['crossed_option'] ?? ($optionCodes[0] ?? 'A'));
        $explicitState = isset($caseSpec['state']) ? (string) $caseSpec['state'] : null;

        if ($explicitState === OmrDetectedItem::STATE_AMBIGUOUS) {
            $state = OmrDetectedItem::STATE_AMBIGUOUS;
            $selected = null;
            $observedOptions = (array) ($caseSpec['observed_options'] ?? array_slice($optionCodes, 0, min(count($optionCodes), 2)));
            $confidence = (float) ($caseSpec['confidence'] ?? 0.35);
            $defaultIssues = [OmrDetectionIssue::CODE_AMBIGUOUS_MARK];
        } elseif (isset($caseSpec['selected_option']) && $caseSpec['selected_option'] !== $crossedOption) {
            $state = OmrDetectedItem::STATE_MARKED;
            $selected = (string) $caseSpec['selected_option'];
            $observedOptions = [$selected];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.85);
            $defaultIssues = [OmrDetectionIssue::CODE_LOW_CONFIDENCE];
        } else {
            $state = OmrDetectedItem::STATE_MARKED;
            $selected = $crossedOption;
            $observedOptions = [$crossedOption];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.68);
            $defaultIssues = [OmrDetectionIssue::CODE_LOW_CONFIDENCE];
        }

        $crossedMean = (float) ($caseSpec['crossed_mean'] ?? 85.0);
        $markedMean = (float) ($caseSpec['marked_mean'] ?? 32.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            if ($option->code === $crossedOption) {
                $classification = 'crossed';
                $mean = $crossedMean;
            } elseif ($state === OmrDetectedItem::STATE_MARKED && $option->code === $selected) {
                $classification = 'marked';
                $mean = $markedMean;
            } elseif ($state === OmrDetectedItem::STATE_AMBIGUOUS && in_array($option->code, $observedOptions, true)) {
                $classification = 'ambiguous_candidate';
                $mean = 34.0;
            } else {
                $classification = 'unmarked';
                $mean = $unmarkedBase + $index;
            }
            $observations[] = new OmrOptionObservation($option->code, $mean, $classification);
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? $defaultIssues);
        $severity = in_array(OmrDetectionIssue::CODE_AMBIGUOUS_MARK, $issueCodes, true)
            ? OmrDetectionIssue::SEVERITY_REQUIRED
            : OmrDetectionIssue::SEVERITY_WARNING;
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, $severity);

        return [
            $state,
            $selected,
            $observedOptions,
            $observations,
            $confidence,
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildPartialCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $partialOption = (string) ($caseSpec['partial_option'] ?? $caseSpec['option'] ?? ($optionCodes[0] ?? 'A'));
        $explicitState = isset($caseSpec['state']) ? (string) $caseSpec['state'] : null;

        if ($explicitState === OmrDetectedItem::STATE_BLANK) {
            $state = OmrDetectedItem::STATE_BLANK;
            $selected = null;
            $observedOptions = [];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.60);
        } else {
            $state = OmrDetectedItem::STATE_MARKED;
            $selected = $partialOption;
            $observedOptions = [$partialOption];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.62);
        }

        $partialMean = (float) ($caseSpec['partial_mean'] ?? 95.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            if ($option->code === $partialOption) {
                $classification = 'partial';
                $mean = $partialMean;
            } else {
                $classification = 'unmarked';
                $mean = $unmarkedBase + $index;
            }
            $observations[] = new OmrOptionObservation($option->code, $mean, $classification);
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? [OmrDetectionIssue::CODE_LOW_CONFIDENCE]);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_WARNING);

        return [
            $state,
            $selected,
            $observedOptions,
            $observations,
            $confidence,
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $optionCodes
     * @param  array<string, mixed>  $caseSpec
     * @return array{
     *     0: string,
     *     1: ?string,
     *     2: list<string>,
     *     3: list<OmrOptionObservation>,
     *     4: float,
     *     5: list<OmrDetectionIssue>,
     *     6: list<string>
     * }
     */
    private function buildOutOfRegionCase(OmrItemGeometry $geometry, array $optionCodes, array $caseSpec): array
    {
        $oorOption = (string) ($caseSpec['out_of_region_option'] ?? $caseSpec['option'] ?? ($optionCodes[0] ?? 'A'));
        $explicitState = isset($caseSpec['state']) ? (string) $caseSpec['state'] : null;

        if ($explicitState === OmrDetectedItem::STATE_UNREADABLE) {
            $state = OmrDetectedItem::STATE_UNREADABLE;
            $selected = null;
            $observedOptions = (array) ($caseSpec['observed_options'] ?? []);
            $confidence = (float) ($caseSpec['confidence'] ?? 0.20);
        } elseif ($explicitState === OmrDetectedItem::STATE_BLANK) {
            $state = OmrDetectedItem::STATE_BLANK;
            $selected = null;
            $observedOptions = [];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.50);
        } else {
            $state = OmrDetectedItem::STATE_MARKED;
            $selected = $oorOption;
            $observedOptions = [$oorOption];
            $confidence = (float) ($caseSpec['confidence'] ?? 0.55);
        }

        $oorMean = (float) ($caseSpec['out_of_region_mean'] ?? 110.0);
        $unmarkedBase = (float) ($caseSpec['unmarked_mean'] ?? 240.0);
        $observations = [];

        foreach ($geometry->options as $index => $option) {
            if ($option->code === $oorOption) {
                $classification = 'out_of_region';
                $mean = $oorMean;
            } else {
                $classification = 'unmarked';
                $mean = $unmarkedBase + $index;
            }
            $observations[] = new OmrOptionObservation($option->code, $mean, $classification);
        }

        $issueCodes = (array) ($caseSpec['issue_codes'] ?? [OmrDetectionIssue::CODE_NO_GEOMETRY]);
        $itemIssues = $this->createItemIssues($geometry, $issueCodes, OmrDetectionIssue::SEVERITY_WARNING);

        return [
            $state,
            $selected,
            $observedOptions,
            $observations,
            $confidence,
            $itemIssues,
            $issueCodes,
        ];
    }

    /**
     * @param  list<string>  $issueCodes
     * @return list<OmrDetectionIssue>
     */
    private function createItemIssues(OmrItemGeometry $geometry, array $issueCodes, string $defaultSeverity): array
    {
        $issues = [];
        foreach ($issueCodes as $code) {
            if (OmrDetectionIssue::isKnownCode($code)) {
                $severity = $code === OmrDetectionIssue::CODE_AMBIGUOUS_MARK || $code === OmrDetectionIssue::CODE_UNREADABLE
                    ? OmrDetectionIssue::SEVERITY_REQUIRED
                    : $defaultSeverity;
                $issues[] = new OmrDetectionIssue($code, $severity, $geometry->areaCode, $geometry->questionNo);
            }
        }

        return $issues;
    }

    /**
     * @return string|array<string, mixed>|null
     */
    private function resolveConfiguredEvidenceCase(OmrDetectionRequest $request, OmrItemGeometry $geometry): mixed
    {
        $cases = config('omr.fake.evidence_cases');
        if (! is_array($cases) || $cases === []) {
            $fallback = config('omr.fake.evidence_case');

            return is_string($fallback) || is_array($fallback) ? $fallback : null;
        }

        $scoped = null;
        if (array_key_exists($request->inputHash, $cases) && is_array($cases[$request->inputHash])) {
            $scoped = $cases[$request->inputHash];
        } else {
            $scoped = $cases;
        }

        $candidates = [
            $geometry->nativeLabel,
            strtoupper($geometry->nativeLabel),
            strtolower($geometry->nativeLabel),
            sprintf('%s_Q_%03d', strtoupper($geometry->areaCode), $geometry->questionNo),
            sprintf('%s_Q_%d', strtoupper($geometry->areaCode), $geometry->questionNo),
            sprintf('%s_Q_%03d', $geometry->areaCode, $geometry->questionNo),
            sprintf('%s_Q_%d', $geometry->areaCode, $geometry->questionNo),
            $geometry->position,
            (string) $geometry->position,
        ];

        foreach ($candidates as $key) {
            if (array_key_exists($key, $scoped)) {
                return $scoped[$key];
            }
        }

        $area = $geometry->areaCode;
        $areaUpper = strtoupper($area);
        if (isset($scoped[$area]) && is_array($scoped[$area])) {
            if (array_key_exists($geometry->questionNo, $scoped[$area])) {
                return $scoped[$area][$geometry->questionNo];
            }
            $pad = sprintf('%03d', $geometry->questionNo);
            if (array_key_exists($pad, $scoped[$area])) {
                return $scoped[$area][$pad];
            }
        }
        if (isset($scoped[$areaUpper]) && is_array($scoped[$areaUpper])) {
            if (array_key_exists($geometry->questionNo, $scoped[$areaUpper])) {
                return $scoped[$areaUpper][$geometry->questionNo];
            }
            $pad = sprintf('%03d', $geometry->questionNo);
            if (array_key_exists($pad, $scoped[$areaUpper])) {
                return $scoped[$areaUpper][$pad];
            }
        }

        if (array_key_exists('*', $scoped)) {
            return $scoped['*'];
        }
        if (array_key_exists('_default', $scoped)) {
            return $scoped['_default'];
        }

        $fallback = config('omr.fake.evidence_case');

        return is_string($fallback) || is_array($fallback) ? $fallback : null;
    }

    /**
     * Preserve one observation for every declared option. Candidate membership
     * is represented only by `observedOptions`; measurements stay opaque.
     *
     * @param  list<string>  $observedOptions
     * @return list<OmrOptionObservation>
     */
    private function optionObservations(OmrItemGeometry $geometry, array $observedOptions, string $state): array
    {
        $observations = [];
        foreach ($geometry->options as $index => $option) {
            $isCandidate = in_array($option->code, $observedOptions, true);
            $classification = match ($state) {
                OmrDetectedItem::STATE_MARKED => $isCandidate ? 'marked' : 'unmarked',
                OmrDetectedItem::STATE_AMBIGUOUS => $isCandidate ? 'ambiguous_candidate' : 'unmarked',
                OmrDetectedItem::STATE_UNREADABLE => $isCandidate ? 'unreadable_candidate' : 'unreadable',
                default => 'unmarked',
            };
            $mean = $isCandidate ? 32.0 + $index : 180.0 + $index;
            $observations[] = new OmrOptionObservation($option->code, $mean, $classification);
        }

        return $observations;
    }

    private function fixtureCorrectCount(OmrDetectionRequest $request, string $areaCode): ?int
    {
        $groundTruth = (array) config('omr.fake.ground_truth', []);

        return isset($groundTruth[$request->inputHash][$areaCode])
            ? (int) $groundTruth[$request->inputHash][$areaCode]
            : null;
    }

    private function identityRegion(OmrDetectionRequest $request): OmrRegion
    {
        return $request->identityRegions[OmrIdentityField::CODE_APPLICANT_NUMBER]
            ?? new OmrRegion(0.0, 0.0, 1.0, 0.08);
    }

    private function fraction(string $seed, int $slice): float
    {
        return hexdec(substr($seed, $slice * 8, 8)) / 4294967295.0;
    }

    private function elapsedMs(float $started): float
    {
        return round((microtime(true) - $started) * 1000, 2);
    }
}
