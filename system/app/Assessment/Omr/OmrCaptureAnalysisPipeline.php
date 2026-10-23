<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use Throwable;

use function array_filter;
use function array_map;
use function array_sum;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_finite;
use function is_numeric;
use function microtime;

/**
 * Shared read-only capture analysis. It preserves one-time EXIF normalization,
 * four-marker gates, and unique marker-only selection. A signed semantic
 * orientation may prioritize work but never weakens layout validation.
 */
final class OmrCaptureAnalysisPipeline implements OmrCaptureAnalysisPort
{
    public function __construct(
        private readonly OmrCapturePreflight $preflight,
        private readonly OmrQuarterTurnNormalizer $normalizer,
        private readonly OmrDiagnosticRequestBuilder $requests,
        private readonly OmrLayoutVerificationPort $layout,
        private readonly OmrTemplateDiagnosticRunner $runner,
        private readonly ?OmrCaptureOrientationPort $orientation = null,
    ) {}

    public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence
    {
        $input = $request->capture;
        $target = $request->target;
        $pageNo = $request->pageNo;

        if ($input === null) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: '',
                code: 'capture_input_missing',
                detail: 'The capture input is missing.',
            );
        }
        if ($target === null) {
            return OmrPreparedCaptureEvidence::failed(
                target: null,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'analysis_target_missing',
                detail: 'The pinned analysis target is missing.',
                input: $input,
            );
        }
        $registrationMode = $target->registrationMode();

        if ($input->clientQuad !== null && $registrationMode !== OmrRegistrationMode::DocumentBound) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'client_quad_registration_mismatch',
                detail: 'Advisory corners are accepted only for document-bound registration.',
                input: $input,
                expectedVectorHash: $request->expectedVectorHash,
            );
        }

        ($request->onPhase)?->__invoke('preflight');
        $preflight = $this->preflight->validate($input);
        if (! $preflight->succeeded()) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: $preflight->code ?? 'capture_preflight_failed',
                detail: $preflight->detail,
                input: $input,
                expectedVectorHash: $request->expectedVectorHash,
            );
        }

        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            return $this->analyzeDocumentBound($request, $input, $target, $pageNo);
        }

        ($request->onPhase)?->__invoke('orientation');
        try {
            $orientationDiagnostics = $this->normalizer->candidates($input);
        } catch (OmrQuarterTurnNormalizationException $exception) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: $exception->errorCode,
                detail: $exception->getMessage(),
                input: $input,
                expectedVectorHash: $request->expectedVectorHash,
            );
        } catch (Throwable) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'rotation_failed',
                detail: 'The capture orientation candidates could not be generated.',
                input: $input,
                expectedVectorHash: $request->expectedVectorHash,
            );
        }
        $trustedOrientation = null;
        if ($request->challengeCode !== null && $this->orientation !== null) {
            $canonical = $orientationDiagnostics->candidates[0];
            $trustedOrientation = $this->orientation->inspect(
                $canonical->bytes,
                $canonical->mime,
                $request->challengeCode,
                $pageNo,
            );
            if ($trustedOrientation !== null) {
                $orientationDiagnostics = $this->prioritize($orientationDiagnostics, $trustedOrientation);
            }
        }

        ($request->onPhase)?->__invoke('layout_verification');
        $records = [];
        $valid = [];
        $layouts = [];
        foreach ($orientationDiagnostics->candidates as $candidate) {
            $started = microtime(true);
            $layout = null;
            $requestEnvelope = null;
            $failureCode = null;
            $failureDetail = null;
            try {
                $requestEnvelope = $target->buildRequest(
                    $this->requests,
                    $candidate->bytes,
                    $candidate->mime,
                    $pageNo,
                );
                $layout = $this->layout->verifyLayout($requestEnvelope);
                $unsafeBlocks = $this->requests->unsafeBlocks(
                    $requestEnvelope->nativeTemplate,
                    $layout->fieldBlockShifts ?? [],
                );
                if ($unsafeBlocks !== []) {
                    $failureCode = 'sheet_misaligned';
                    $failureDetail = 'The registered field blocks shifted across a native option boundary.';
                } elseif (! $this->validLayout($layout)) {
                    $failureCode = $this->layoutFailureCode($layout);
                    $failureDetail = $layout->detail;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failureCode = 'analysis_failed';
                $failureDetail = 'The capture could not be registered against the required four-marker frame.';
            }

            $elapsed = (microtime(true) - $started) * 1000;
            $layouts[$candidate->orientation] = $layout;
            $records[] = [
                'orientation' => $candidate->orientation,
                'candidate_hash' => $candidate->sha256,
                'mime' => $candidate->mime,
                'width' => $candidate->width,
                'height' => $candidate->height,
                'status' => $failureCode === null ? 'layout_valid' : 'layout_failed',
                'code' => $failureCode,
                'detail' => $failureDetail,
                'marker_matches' => $layout?->markerMatches,
                'gates' => $layout?->gates,
                'duration_ms' => round($elapsed, 3),
            ];
            if ($failureCode === null && $layout !== null && $requestEnvelope !== null) {
                $valid[] = [
                    'candidate' => $candidate,
                    'layout' => $layout,
                    'request' => $requestEnvelope,
                    'score' => $this->layoutScore($layout),
                ];
            }
            if ($trustedOrientation !== null
                && $candidate->orientation === $trustedOrientation
                && $failureCode === null) {
                break;
            }
        }

        $diagnostics = new OmrQuarterTurnDiagnostics(
            candidates: $orientationDiagnostics->candidates,
            failures: array_values(array_filter($records, static fn (array $record): bool => $record['status'] !== 'layout_valid')),
            timings: [
                ...$orientationDiagnostics->timings,
                'layout_ms' => round(array_sum(array_map(static fn (array $record): float => (float) $record['duration_ms'], $records)), 3),
            ],
            results: $records,
        );

        if ($trustedOrientation !== null
            && $valid !== []
            && array_filter(
                $valid,
                static fn (array $entry): bool => $entry['candidate']->orientation === $trustedOrientation,
            ) === []) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'orientation_mismatch',
                detail: 'The marker-valid orientation contradicts the signed QR orientation.',
                input: $input,
                orientationDiagnostics: $diagnostics,
                layout: $layouts[$trustedOrientation] ?? null,
                expectedVectorHash: $request->expectedVectorHash,
            );
        }

        $winner = $this->winner($valid);
        if ($winner === null) {
            [$code, $detail] = $this->resolveFailure($records, $valid);

            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: $code,
                detail: $detail,
                input: $input,
                orientationDiagnostics: $diagnostics,
                layout: $layouts[$orientationDiagnostics->candidates[0]->orientation ?? 0] ?? null,
                expectedVectorHash: $request->expectedVectorHash,
            );
        }

        ($request->onPhase)?->__invoke('recognition');
        try {
            $diagnostic = $this->runner->verify(
                target: $target,
                bytes: $winner['candidate']->bytes,
                mime: $winner['candidate']->mime,
                plan: $request->plan,
                pageNo: $pageNo,
                verifiedLayout: $winner['layout'],
                preparedRequest: $winner['request'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'analysis_failed',
                detail: 'The capture analysis could not be completed.',
                input: $input,
                orientationDiagnostics: $diagnostics,
                layout: $winner['layout'],
                expectedVectorHash: $request->expectedVectorHash,
                detectionRequest: $winner['request'],
            );
        }

        return OmrPreparedCaptureEvidence::fromDiagnostic(
            target: $target,
            input: $input,
            pageNo: $pageNo,
            orientationDiagnostics: $diagnostics,
            diagnostic: $diagnostic,
            acceptedOrientation: $winner['candidate']->orientation,
            layout: $winner['layout'],
            providerResult: $diagnostic->providerResult,
            detectionRequest: $winner['request'],
            expectedVectorHash: $request->expectedVectorHash,
        );
    }

    private function analyzeDocumentBound(
        OmrCaptureAnalysisRequest $analysis,
        OmrCaptureInput $input,
        OmrDiagnosticTarget $target,
        int $pageNo,
    ): OmrPreparedCaptureEvidence {
        $orientationDiagnostics = new OmrQuarterTurnDiagnostics([]);
        if ($input->isPdf()) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'document_bound_image_required',
                detail: 'Document-bound registration requires one decoded source image per page.',
                input: $input,
                orientationDiagnostics: $orientationDiagnostics,
                expectedVectorHash: $analysis->expectedVectorHash,
            );
        }

        try {
            $request = $target->buildRequest($this->requests, $input->bytes, $input->mime, $pageNo);
            if ($input->clientQuad !== null) {
                $sourceImage = OmrSourceImage::fromBytes($input->bytes);
                if (! $input->clientQuad->isBoundTo($sourceImage, $pageNo)) {
                    throw new \RuntimeException('The advisory quad is not bound to this source image and page.');
                }
                $request = $this->requests->rebindImage(
                    $request,
                    $input->bytes,
                    $input->mime,
                    $pageNo,
                    $input->clientQuad,
                );
            }
        } catch (Throwable) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'analysis_request_invalid',
                detail: 'The pinned document-bound request or advisory quad is invalid.',
                input: $input,
                orientationDiagnostics: $orientationDiagnostics,
                expectedVectorHash: $analysis->expectedVectorHash,
            );
        }
        $cropPolicy = $input->cropPolicy ?? 'auto_document_quad';
        $request = $request->withCropPolicy($cropPolicy);

        ($analysis->onPhase)?->__invoke('layout_verification');
        try {
            $layout = $this->layout->verifyLayout($request);
        } catch (Throwable) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'layout_verification_failed',
                detail: $cropPolicy === 'keep_original'
                    ? 'The original page layout could not be verified.'
                    : 'The document boundary could not be verified.',
                input: $input,
                orientationDiagnostics: $orientationDiagnostics,
                expectedVectorHash: $analysis->expectedVectorHash,
                detectionRequest: $request,
            );
        }

        $rectification = $layout->rectification;
        $keepOriginal = ($rectification['crop_policy'] ?? null) === 'keep_original';
        $unsafe = $this->requests->unsafeBlocks($request->nativeTemplate, $layout->fieldBlockShifts ?? []);
        if (! $layout->succeeded()
            || ! OmrDocumentRectification::isAccepted($rectification)
            || $unsafe !== []) {
            $code = $unsafe !== []
                ? 'sheet_misaligned'
                : ($layout->code ?? 'document_rectification_rejected');

            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: $code,
                detail: $layout->detail ?? ($cropPolicy === 'keep_original'
                    ? 'The original page did not satisfy its layout checks.'
                    : 'The document boundary did not satisfy its rectification gates.'),
                input: $input,
                orientationDiagnostics: $orientationDiagnostics,
                layout: $layout,
                gates: [
                    'registration' => OmrDocumentRectification::isAccepted($rectification) ? 'pass' : 'fail',
                    'content' => $keepOriginal || (($rectification['content_gate']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                    'document' => $keepOriginal || (($rectification['document_gates']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                    'shifts' => $unsafe === [] ? 'pass' : 'fail',
                    'unsafe_blocks' => $unsafe,
                ],
                expectedVectorHash: $analysis->expectedVectorHash,
                detectionRequest: $request,
            );
        }

        ($analysis->onPhase)?->__invoke('recognition');
        try {
            $diagnostic = $this->runner->verify(
                target: $target,
                bytes: $input->bytes,
                mime: $input->mime,
                plan: $analysis->plan,
                pageNo: $pageNo,
                verifiedLayout: $layout,
                preparedRequest: $request,
            );
        } catch (Throwable) {
            return OmrPreparedCaptureEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                inputHash: $input->sourceHash(),
                code: 'analysis_failed',
                detail: 'The document-bound capture analysis could not be completed.',
                input: $input,
                orientationDiagnostics: $orientationDiagnostics,
                layout: $layout,
                expectedVectorHash: $analysis->expectedVectorHash,
                detectionRequest: $request,
            );
        }

        return OmrPreparedCaptureEvidence::fromDiagnostic(
            target: $target,
            input: $input,
            pageNo: $pageNo,
            orientationDiagnostics: new OmrQuarterTurnDiagnostics([]),
            diagnostic: $diagnostic,
            acceptedOrientation: null,
            layout: $layout,
            providerResult: $diagnostic->providerResult,
            detectionRequest: $request,
            expectedVectorHash: $analysis->expectedVectorHash,
        );
    }

    private function prioritize(OmrQuarterTurnDiagnostics $diagnostics, int $orientation): OmrQuarterTurnDiagnostics
    {
        $preferred = array_values(array_filter(
            $diagnostics->candidates,
            static fn (OmrQuarterTurnCandidate $candidate): bool => $candidate->orientation === $orientation,
        ));
        $remaining = array_values(array_filter(
            $diagnostics->candidates,
            static fn (OmrQuarterTurnCandidate $candidate): bool => $candidate->orientation !== $orientation,
        ));

        return new OmrQuarterTurnDiagnostics(
            candidates: [...$preferred, ...$remaining],
            failures: $diagnostics->failures,
            timings: $diagnostics->timings,
            results: $diagnostics->results,
        );
    }

    private function validLayout(OmrLayoutVerificationResult $layout): bool
    {
        if (! $layout->succeeded() || ! is_array($layout->markerMatches) || count($layout->markerMatches) !== 4) {
            return false;
        }
        foreach ($layout->markerMatches as $score) {
            if (! is_numeric($score) || ! is_finite((float) $score)) {
                return false;
            }
        }
        if (is_array($layout->gates)) {
            foreach ($layout->gates as $name => $status) {
                if ($name !== 'unsafe_blocks' && $status !== 'pass') {
                    return false;
                }
            }
        }

        return true;
    }

    private function layoutFailureCode(OmrLayoutVerificationResult $layout): string
    {
        if (is_array($layout->markerMatches) && count($layout->markerMatches) === 4) {
            return 'marker_mismatch';
        }

        return in_array($layout->code, ['marker_missing', 'marker_not_found', 'markers_not_found'], true)
            ? 'marker_missing'
            : ($layout->code ?? 'marker_mismatch');
    }

    private function layoutScore(OmrLayoutVerificationResult $layout): float
    {
        return array_sum(array_map(static fn (mixed $score): float => (float) $score, (array) $layout->markerMatches));
    }

    /** @param list<array{candidate: OmrQuarterTurnCandidate, layout: OmrLayoutVerificationResult, request: OmrDetectionRequest, score: float}> $valid */
    private function winner(array $valid): ?array
    {
        if ($valid === []) {
            return null;
        }
        $bestScore = max(array_map(static fn (array $entry): float => $entry['score'], $valid));
        $best = array_values(array_filter(
            $valid,
            static fn (array $entry): bool => $entry['score'] === $bestScore,
        ));
        if (count($best) !== 1) {
            return null;
        }

        return $best[0];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<array{candidate: OmrQuarterTurnCandidate, layout: OmrLayoutVerificationResult, request: OmrDetectionRequest, score: float}>  $valid
     * @return array{0: string, 1: string}
     */
    private function resolveFailure(array $records, array $valid): array
    {
        if ($valid !== []) {
            return [
                'marker_mismatch',
                'No unique quarter-turn candidate satisfied the four-marker layout gates.',
            ];
        }

        $unsafeFailure = array_values(array_filter(
            $records,
            static fn (array $record): bool => ($record['code'] ?? null) === 'sheet_misaligned',
        ));
        if ($unsafeFailure !== []) {
            return [
                'sheet_misaligned',
                'The registered field blocks crossed a native option boundary.',
            ];
        }

        $transportCodes = [
            'multipart_part_too_large',
            'request_too_large',
            'worker_http_413',
            'worker_unavailable',
            'worker_timeout',
            'child_timeout',
            'worker_busy',
            'worker_http_429',
            'worker_http_500',
            'worker_http_502',
            'worker_http_503',
            'worker_http_504',
            'worker_auth_invalid',
            'worker_configuration_invalid',
            'worker_output_invalid',
            'child_output_invalid',
            'candidate_size_exceeded',
            'pixel_budget_exceeded',
            'image_decode_failed',
        ];

        $visualRecords = array_values(array_filter(
            $records,
            static fn (array $record): bool => ! in_array($record['code'] ?? '', $transportCodes, true),
        ));

        if ($visualRecords === []) {
            $firstRecord = $records[0] ?? [];
            $transportCode = (string) ($firstRecord['code'] ?? 'worker_unavailable');
            $transportDetail = (string) ($firstRecord['detail'] ?? 'The OMR worker could not process the capture request.');

            return [$transportCode, $transportDetail];
        }

        $partialRecords = array_values(array_filter(
            $visualRecords,
            static fn (array $record): bool => ($record['code'] ?? null) === 'sheet_partial',
        ));
        if ($partialRecords !== []) {
            return [
                'sheet_partial',
                (string) ($partialRecords[0]['detail'] ?? 'Part of the sheet is out of frame or alignment markers were only partially detected.'),
            ];
        }
        $hasFourMarkerObservation = count(array_filter(
            $visualRecords,
            static fn (array $record): bool => is_array($record['marker_matches'] ?? null)
                && count($record['marker_matches']) === 4,
        )) > 0;
        if ($hasFourMarkerObservation) {
            return [
                'marker_mismatch',
                'No unique quarter-turn candidate satisfied the four-marker layout gates.',
            ];
        }

        $mismatchRecords = array_values(array_filter(
            $visualRecords,
            static fn (array $record): bool => ($record['code'] ?? null) === 'marker_mismatch',
        ));
        if ($mismatchRecords !== []) {
            return [
                'marker_mismatch',
                (string) ($mismatchRecords[0]['detail'] ?? 'The alignment markers do not match the expected marker profile geometry.'),
            ];
        }

        $otherVisual = array_values(array_filter(
            $visualRecords,
            static fn (array $record): bool => ! in_array($record['code'] ?? '', ['marker_missing', 'marker_not_found', 'markers_not_found'], true),
        ));
        if ($otherVisual !== []) {
            return [
                (string) ($otherVisual[0]['code'] ?? 'marker_mismatch'),
                (string) ($otherVisual[0]['detail'] ?? 'Layout verification could not be completed.'),
            ];
        }

        return [
            'marker_missing',
            'All four required alignment markers were not detected.',
        ];
    }
}
