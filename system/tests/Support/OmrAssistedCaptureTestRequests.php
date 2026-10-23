<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @param  array<string, mixed>  $fields  Positive page_no selects the GET target only; nonpositive values remain explicit negative body cases.
 * @param  array{operation_id?: string, idempotency_key?: string}|null  $identity
 * @return array<string, mixed>
 */
function omrAssistedCapturePayload(
    TestCase $test,
    string $showUrl,
    array $fields = [],
    ?array $identity = null,
): array {
    $requestedPageNo = $fields['page_no'] ?? 1;
    $hasPositivePageSelector = (is_int($requestedPageNo) && $requestedPageNo > 0)
        || (is_string($requestedPageNo) && ctype_digit($requestedPageNo) && (int) $requestedPageNo > 0);
    if ($hasPositivePageSelector) {
        $separator = str_contains($showUrl, '?') ? '&' : '?';
        $showUrl .= $separator.'capture_page_no='.rawurlencode((string) $requestedPageNo);
        unset($fields['page_no']);
    }

    $projection = $test->getJson($showUrl)->assertOk()->json();
    $target = $projection['context']['capture_target'] ?? null;
    if (! is_array($target)) {
        throw new RuntimeException('OMR capture projection did not provide context.capture_target.');
    }
    foreach (['client_capture_id', 'page_no', 'target_key'] as $requiredTargetField) {
        if (! array_key_exists($requiredTargetField, $target)) {
            throw new RuntimeException("OMR capture target is missing {$requiredTargetField}.");
        }
    }

    $pendingCaptureId = $target['pending_capture_id'] ?? null;
    $pendingCaptureRevision = $target['pending_capture_revision'] ?? null;
    $payload = [
        'expected_revision' => $projection['revision'] ?? throw new RuntimeException('OMR projection is missing revision.'),
        'client_capture_id' => $target['client_capture_id'],
        'page_no' => $target['page_no'],
        'target_key' => $target['target_key'],
        'operation_id' => $identity['operation_id'] ?? (string) Str::uuid(),
        'idempotency_key' => $identity['idempotency_key'] ?? (string) Str::uuid(),
        // Test-local camera adapter revisions; these do not assert physical device qualification.
        'source_generation' => 1,
        'config_revision' => 1,
    ];

    if (isset($target['target_revision']) && is_numeric($target['target_revision'])) {
        $payload['target_revision'] = (int) $target['target_revision'];
    }
    if (isset($target['instance_id']) && is_string($target['instance_id']) && $target['instance_id'] !== '') {
        $payload['instance_id'] = $target['instance_id'];
    }
    if (isset($target['sheet_id']) && is_numeric($target['sheet_id'])) {
        $payload['sheet_id'] = (int) $target['sheet_id'];
    }
    if (($projection['execution_kind'] ?? null) === 'production' && ! array_key_exists('capture_mode', $fields)) {
        $payload['capture_mode'] = 'auto';
    }
    if (is_string($pendingCaptureId)) {
        $payload['pending_capture_id'] = $pendingCaptureId;
    }
    if (is_int($pendingCaptureRevision) || (is_string($pendingCaptureRevision) && ctype_digit($pendingCaptureRevision))) {
        $payload['pending_capture_revision'] = (int) $pendingCaptureRevision;
    }

    return [...$payload, ...$fields];
}
