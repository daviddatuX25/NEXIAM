<?php

namespace App\Assessment\Omr;

use function is_finite;
use function getimagesizefromstring;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strlen;

/**
 * Validates provider observations against the exact v2 request. This is the
 * only boundary before persistence; answer-key comparison happens in NEXIAM.
 *
 * Returns the effective success: the provider payload unchanged when it is
 * well-formed and safely aligned, or an amended copy carrying a required
 * `sheet_misaligned` issue when a field-block shift can cross the native
 * option/row pitch. Callers MUST persist and project the returned value, and
 * MUST suppress answer/score proposals whenever that required issue is present.
 * Non-succeeded outcomes pass through and yield null for the caller to handle.
 *
 * @throws OmrInvalidDetectionOutput
 */
final class OmrDetectionResultValidator
{
    private const CALIBRATION_STATUSES = ['synthetic', 'unqualified', 'qualified'];

    /**
     * A shift reaching half the option pitch puts a bubble center at the
     * boundary of the neighbouring option, so readings are at best ambiguous
     * and may be fabricated as the adjacent option. Conservative: gate at
     * half the native pitch, never at full image bounds.
     */
    private const SAFETY_SHIFT_FRACTION = 0.5;

    /**
     * @throws OmrInvalidDetectionOutput
     */
    public function validate(OmrDetectionRequest $request, OmrDetectionResult $result): ?OmrDetectionSuccess
    {
        if ($result->outcome !== OmrDetectionOutcome::Succeeded) {
            return null;
        }

        $success = $result->success;
        if ($success === null) {
            throw new OmrInvalidDetectionOutput('Succeeded outcome is missing its success payload.');
        }

        $errors = $this->validateSuccess($request, $success);

        if ($errors !== []) {
            throw new OmrInvalidDetectionOutput(
                'Provider output failed validation: '.implode('; ', array_slice($errors, 0, 20)),
            );
        }

        if ($this->unsafeFieldBlockShifts($request, $success) !== []) {
            return $this->withRequiredMisalignment($success);
        }

        return $success;
    }

    /** @return list<string> */
    private function validateSuccess(OmrDetectionRequest $request, OmrDetectionSuccess $success): array
    {
        $errors = [];

        if ($request->envelopeVersion !== OmrDetectionRequest::ENVELOPE_VERSION
            || $success->responseEnvelopeVersion !== $request->envelopeVersion) {
            $errors[] = 'response envelope version does not match the v2 request';
        }

        foreach ([
            'input_hash' => [$success->inputHash, $request->inputHash],
            'config_hash' => [$success->configHash, $request->configHash],
            'run_id' => [$success->runId, $request->runId],
            'correlation_id' => [$success->correlationId, $request->correlationId],
            'booklet_hash' => [$success->bookletHash, $request->bookletHash],
            'native_bundle_hash' => [$success->nativeBundleHash, $request->nativeBundleHash],
            'manifest_hash' => [$success->manifestHash, $request->manifestHash],
            'patch_identity' => [$success->patchIdentity, $request->patchIdentity],
            'engine_name' => [$success->engineName, $request->engineName],
            'engine_commit' => [$success->engineCommit, $request->engineCommit],
        ] as $name => [$actual, $expected]) {
            if ($actual !== $expected) {
                $errors[] = $name.' does not match the request';
            }
        }

        if ($success->imageMime !== $request->imageMime
            || ($request->imageWidth !== null && $success->imageWidth !== $request->imageWidth)
            || ($request->imageHeight !== null && $success->imageHeight !== $request->imageHeight)) {
            $errors[] = 'decoded image metadata does not match the request';
        }

        if (! in_array($success->calibrationStatus, self::CALIBRATION_STATUSES, true)) {
            $errors[] = "unknown calibration status '{$success->calibrationStatus}'";
        }

        $allowConfidence = $success->calibrationStatus !== 'unqualified';
        if ($allowConfidence) {
            if (! $this->isConfidence($success->sheetConfidence)) {
                $errors[] = 'sheet_confidence must be finite and within [0, 1]';
            }

            if (! $this->isConfidence($success->applicantNumberConfidence)) {
                $errors[] = 'applicant_number_confidence must be finite and within [0, 1]';
            }
        } elseif ($success->sheetConfidence !== null || $success->applicantNumberConfidence !== null) {
            $errors[] = 'unqualified provider output must leave confidence null';
        }

        if ($success->applicantNumberProposal === null
            && $success->applicantNumberConfidence !== null
            && $success->applicantNumberConfidence > 0.0) {
            $errors[] = 'applicant_number_confidence must be zero when no proposal is present';
        }

        $labels = $request->expectedFieldLabels;
        if (count($labels) !== count(array_unique($labels))) {
            $errors[] = 'expected field labels must be unique';
        }

        foreach ($success->identityFields as $field) {
            if (! in_array($field->code, [OmrIdentityField::CODE_APPLICANT_NUMBER, OmrIdentityField::CODE_NAME, OmrIdentityField::CODE_BIRTHDATE], true)) {
                $errors[] = "unknown identity field code '{$field->code}'";
            }

            if ($success->calibrationStatus === 'unqualified' && $field->code !== OmrIdentityField::CODE_APPLICANT_NUMBER) {
                $errors[] = "unqualified provider emitted unsupported identity field '{$field->code}'";
            }

            if ($field->code === OmrIdentityField::CODE_APPLICANT_NUMBER && $field->value !== null
                && preg_match('/^[0-9]{1,32}$/', $field->value) !== 1) {
                $errors[] = 'applicant-number observation is malformed';
            }

            if ($allowConfidence ? ! $this->isConfidence($field->confidence) : $field->confidence !== null) {
                $errors[] = "identity field '{$field->code}' confidence is invalid for calibration status";
            }

            if (! $field->region->isFiniteAndNormalized()) {
                $errors[] = "identity field '{$field->code}' has an invalid region";
            }
        }

        $errors = [...$errors, ...$this->validateItemCoverage($request, $success, $allowConfidence)];

        foreach ($success->issues as $issue) {
            if (! OmrDetectionIssue::isKnownCode($issue->code)) {
                $errors[] = "unknown issue code '{$issue->code}'";
            }

            if (! OmrDetectionIssue::isKnownSeverity($issue->severity)) {
                $errors[] = "unknown issue severity '{$issue->severity}'";
            }

            if (($issue->areaCode === null) !== ($issue->questionNo === null)) {
                $errors[] = "issue '{$issue->code}' has an incomplete item reference";
            } elseif ($issue->areaCode !== null && ! $this->hasItem($request, $issue->areaCode, (int) $issue->questionNo)) {
                $errors[] = "issue '{$issue->code}' references an unknown item";
            }
        }

        if ($success->calibrationStatus === 'unqualified') {
            $errors = [...$errors, ...$this->validateAlignedImage($success)];
            $errors = [...$errors, ...$this->validateFieldBlockShifts($request, $success)];
        }

        return $errors;
    }

    /** @return list<string> */
    private function validateItemCoverage(OmrDetectionRequest $request, OmrDetectionSuccess $success, bool $allowConfidence): array
    {
        $errors = [];
        $expected = [];

        foreach ($request->items as $geometry) {
            $expected[$geometry->position] = $geometry;
        }

        $seen = [];
        foreach ($success->items as $item) {
            $geometry = $expected[$item->position] ?? null;

            if ($geometry === null) {
                $errors[] = "detected item at position {$item->position} is not part of the request";

                continue;
            }

            if (isset($seen[$item->position])) {
                $errors[] = "duplicate detected item at position {$item->position}";
            }
            $seen[$item->position] = true;

            if ($item->areaCode !== $geometry->areaCode) {
                $errors[] = "item {$item->position} area_code does not match the request";
            }

            if ($item->questionNo !== $geometry->questionNo) {
                $errors[] = "item {$item->position} question_no does not match the request";
            }

            if (! in_array($item->state, OmrDetectedItem::STATES, true)) {
                $errors[] = "item {$item->position} has an unknown state '{$item->state}'";
            }

            if ($item->state === OmrDetectedItem::STATE_MARKED && $item->selectedOption === null) {
                $errors[] = "item {$item->position} is marked without a selected option";
            }

            if ($item->selectedOption !== null && ! in_array($item->selectedOption, $geometry->optionCodes(), true)) {
                $errors[] = "item {$item->position} selected option '{$item->selectedOption}' is not a valid option";
            }

            if ($item->state !== OmrDetectedItem::STATE_MARKED && $item->selectedOption !== null) {
                $errors[] = "item {$item->position} carries a selected option in state '{$item->state}'";
            }

            if ($allowConfidence ? ! $this->isConfidence($item->confidence) : $item->confidence !== null) {
                $errors[] = "item {$item->position} confidence is invalid for calibration status";
            }

            if (! $item->region->isFiniteAndNormalized()) {
                $errors[] = "item {$item->position} has an invalid region";
            }
        }

        foreach ($expected as $position => $geometry) {
            if (! isset($seen[$position])) {
                $errors[] = "missing detected item at position {$position}";
            }
        }

        return $errors;
    }

    private function hasItem(OmrDetectionRequest $request, string $areaCode, int $questionNo): bool
    {
        foreach ($request->items as $item) {
            if ($item->areaCode === $areaCode && $item->questionNo === $questionNo) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function validateAlignedImage(OmrDetectionSuccess $success): array
    {
        $aligned = $success->alignedImage;
        if (! is_array($aligned) || ! is_string($aligned['bytes'] ?? null) || ! is_string($aligned['hash'] ?? null)
            || ($aligned['mime'] ?? null) !== 'image/png' || ! is_int($aligned['width'] ?? null) || ! is_int($aligned['height'] ?? null)) {
            return ['unqualified provider must return a canonical aligned PNG'];
        }
        if ($aligned['bytes'] === '' || strlen($aligned['bytes']) > (int) config('omr.omrchecker.max_aligned_bytes', 15 * 1024 * 1024)
            || hash('sha256', $aligned['bytes']) !== $aligned['hash']) {
            return ['aligned image hash or size is invalid'];
        }
        $dimensions = getimagesizefromstring($aligned['bytes']);
        if ($dimensions === false || ($dimensions['mime'] ?? null) !== 'image/png'
            || (int) $dimensions[0] !== $aligned['width'] || (int) $dimensions[1] !== $aligned['height']
            || $aligned['width'] <= 0 || $aligned['height'] <= 0
            || $aligned['width'] * $aligned['height'] > (int) config('omr.upload.max_pixels', 20_000_000)) {
            return ['aligned image metadata is invalid'];
        }

        return [];
    }

    /** @return list<string> */
    private function validateFieldBlockShifts(OmrDetectionRequest $request, OmrDetectionSuccess $success): array
    {
        $expected = array_keys((array) ($request->nativeTemplate['fieldBlocks'] ?? []));
        $seen = [];
        $errors = [];
        foreach ($success->fieldBlockShifts as $shift) {
            if (! is_array($shift) || ! is_string($shift['block'] ?? null)
                || ! is_int($shift['x'] ?? null) || ! is_int($shift['y'] ?? null)) {
                $errors[] = 'field-block shift is malformed';
                continue;
            }
            if (! in_array($shift['block'], $expected, true) || isset($seen[$shift['block']])) {
                $errors[] = 'field-block shift coverage is invalid';
            }
            if (abs($shift['x']) > (int) ($request->imageWidth ?? 0) || abs($shift['y']) > (int) ($request->imageHeight ?? 0)) {
                $errors[] = 'field-block shift exceeds image bounds';
            }
            $seen[$shift['block']] = true;
        }
        if (array_diff($expected, array_keys($seen)) !== []) {
            $errors[] = 'field-block shift coverage is incomplete';
        }

        return $errors;
    }

    /**
     * Field-block names whose reported shift can cross the native option or
     * row pitch of their block. The pitch is derived from the pinned native
     * block geometry (bubblesGap/labelsGap and orientation), so the gate
     * adapts to compact and legacy sheets alike and needs no calibration.
     *
     * @return list<string>
     */
    private function unsafeFieldBlockShifts(OmrDetectionRequest $request, OmrDetectionSuccess $success): array
    {
        $blocks = $request->nativeTemplate['fieldBlocks'] ?? null;
        if (! is_array($blocks) || $blocks === []) {
            return [];
        }

        $unsafe = [];
        foreach ($success->fieldBlockShifts as $shift) {
            if (! is_array($shift) || ! is_string($shift['block'] ?? null)
                || ! is_int($shift['x'] ?? null) || ! is_int($shift['y'] ?? null)) {
                continue;
            }

            $block = $blocks[$shift['block']] ?? null;
            if (! is_array($block)) {
                continue;
            }

            [$pitchX, $pitchY] = $this->optionPitch($block);
            $crosses = ($pitchX !== null && abs($shift['x']) >= $pitchX * self::SAFETY_SHIFT_FRACTION)
                || ($pitchY !== null && abs($shift['y']) >= $pitchY * self::SAFETY_SHIFT_FRACTION);

            if ($crosses) {
                $unsafe[] = (string) $shift['block'];
            }
        }

        return array_values(array_unique($unsafe));
    }

    /**
     * Native option pitch in pixels for one field block: the center-to-center
     * distance between neighbouring bubbles along each axis. OMRChecker lays
     * MCQ4 rows along `direction` (option pitch = bubblesGap, row pitch =
     * labelsGap) and stacks QTYPE_INT digit grids vertically (digit pitch =
     * bubblesGap, column pitch = labelsGap). Unknown orientations degrade to
     * the smaller known gap on both axes; a null axis has no derivable pitch.
     *
     * @param  array<string, mixed>  $block
     * @return array{0: ?float, 1: ?float}
     */
    private function optionPitch(array $block): array
    {
        $bubblesGap = (float) ($block['bubblesGap'] ?? 0);
        $labelsGap = (float) ($block['labelsGap'] ?? 0);
        $direction = (string) ($block['direction'] ?? '');
        $fieldType = (string) ($block['fieldType'] ?? '');

        if ($fieldType === 'QTYPE_INT' || $direction === 'vertical') {
            return [
                $labelsGap > 0 ? $labelsGap : null,
                $bubblesGap > 0 ? $bubblesGap : null,
            ];
        }
        if ($direction === 'horizontal') {
            return [
                $bubblesGap > 0 ? $bubblesGap : null,
                $labelsGap > 0 ? $labelsGap : null,
            ];
        }

        $gaps = array_values(array_filter(
            [$bubblesGap, $labelsGap],
            static fn (float $gap): bool => $gap > 0,
        ));
        if ($gaps === []) {
            return [null, null];
        }
        $pitch = min($gaps);

        return [$pitch, $pitch];
    }

    /**
     * Amends the success so a required `sheet_misaligned` issue is present:
     * the provider's own required issue is retained; otherwise one is derived
     * from the geometry gate so review can fail closed without a fabricated
     * answer proposal.
     */
    private function withRequiredMisalignment(OmrDetectionSuccess $success): OmrDetectionSuccess
    {
        foreach ($success->issues as $issue) {
            if ($issue->code === OmrDetectionIssue::CODE_SHEET_MISALIGNED
                && $issue->severity === OmrDetectionIssue::SEVERITY_REQUIRED) {
                return $success;
            }
        }

        return new OmrDetectionSuccess(
            engineName: $success->engineName,
            engineVersion: $success->engineVersion,
            engineCommit: $success->engineCommit,
            inputHash: $success->inputHash,
            configHash: $success->configHash,
            applicantNumberProposal: $success->applicantNumberProposal,
            applicantNumberConfidence: $success->applicantNumberConfidence,
            identityFields: $success->identityFields,
            items: $success->items,
            issues: [...$success->issues, new OmrDetectionIssue(
                OmrDetectionIssue::CODE_SHEET_MISALIGNED,
                OmrDetectionIssue::SEVERITY_REQUIRED,
            )],
            sheetConfidence: $success->sheetConfidence,
            responseEnvelopeVersion: $success->responseEnvelopeVersion,
            runId: $success->runId,
            correlationId: $success->correlationId,
            bookletHash: $success->bookletHash,
            nativeBundleHash: $success->nativeBundleHash,
            manifestHash: $success->manifestHash,
            patchIdentity: $success->patchIdentity,
            calibrationStatus: $success->calibrationStatus,
            providerDiagnostics: $success->providerDiagnostics,
            fieldBlockShifts: $success->fieldBlockShifts,
            alignedImage: $success->alignedImage,
            imageMime: $success->imageMime,
            imageWidth: $success->imageWidth,
            imageHeight: $success->imageHeight,
            birthdateProposal: $success->birthdateProposal,
        );
    }

    private function isConfidence(?float $value): bool
    {
        return $value !== null && is_finite($value) && $value >= 0.0 && $value <= 1.0;
    }
}
