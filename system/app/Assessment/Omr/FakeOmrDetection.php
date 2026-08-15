<?php

namespace App\Assessment\Omr;

use Throwable;

/**
 * Deterministic synthetic detector used only for the non-production demo. It
 * receives no roster or answer key and emits observation-only v2 output.
 */
final class FakeOmrDetection implements OmrDetectionPort
{
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
            [$state, $selectedOption, $confidence, $itemIssues] = $this->roll($request, $geometry);
            $items[] = new OmrDetectedItem(
                position: $geometry->position,
                areaCode: $geometry->areaCode,
                questionNo: $geometry->questionNo,
                selectedOption: $selectedOption,
                state: $state,
                confidence: $confidence,
                region: $geometry->region,
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
        );
    }

    /** @return array{string, ?string, float, list<OmrDetectionIssue>} */
    private function roll(OmrDetectionRequest $request, OmrItemGeometry $geometry): array
    {
        $seed = hash('sha256', $request->inputHash.'|'.$geometry->areaCode.'|'.$geometry->questionNo);
        $roll = $this->fraction($seed, 0);
        $fixtureCorrect = $this->fixtureCorrectCount($request, $geometry->areaCode);
        $selectedOption = $geometry->options[0]->code ?? null;

        if ($fixtureCorrect !== null) {
            $isMarked = $geometry->questionNo <= $fixtureCorrect;

            return [
                $isMarked ? OmrDetectedItem::STATE_MARKED : OmrDetectedItem::STATE_BLANK,
                $isMarked ? $selectedOption : null,
                $isMarked ? 0.98 : 0.78,
                [],
            ];
        }

        if ($roll < 0.70) {
            return [
                OmrDetectedItem::STATE_MARKED,
                $selectedOption,
                round(0.90 + $this->fraction($seed, 1) * 0.09, 4),
                [],
            ];
        }

        if ($roll < 0.82) {
            return [OmrDetectedItem::STATE_BLANK, null, 0.72, []];
        }

        if ($roll < 0.91) {
            return [
                OmrDetectedItem::STATE_AMBIGUOUS,
                null,
                0.31,
                [new OmrDetectionIssue(
                    OmrDetectionIssue::CODE_AMBIGUOUS_MARK,
                    OmrDetectionIssue::SEVERITY_REQUIRED,
                    $geometry->areaCode,
                    $geometry->questionNo,
                )],
            ];
        }

        return [
            OmrDetectedItem::STATE_UNREADABLE,
            null,
            0.22,
            [new OmrDetectionIssue(
                OmrDetectionIssue::CODE_UNREADABLE,
                OmrDetectionIssue::SEVERITY_REQUIRED,
                $geometry->areaCode,
                $geometry->questionNo,
            )],
        ];
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
