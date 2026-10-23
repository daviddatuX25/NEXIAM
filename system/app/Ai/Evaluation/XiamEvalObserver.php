<?php

namespace App\Ai\Evaluation;

use App\Ai\Policy\InputMinimizer;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Opt-in, process-local observation seam for synthetic Xiam evaluations.
 *
 * The observer deliberately has no persistence, logging, authorization, or event dependencies.
 */
final class XiamEvalObserver
{
    private const MAX_RECORDS = 100;

    private const MAX_TRIAL_BYTES = 262144;

    private const MAX_STRING_BYTES = 2048;

    private const MAX_LIST_ITEMS = 50;

    private const MAX_DEPTH = 8;

    /** @var list<array<string, mixed>> */
    private array $records = [];

    private ?string $trialId = null;

    private int $recordBytes = 0;

    public function __construct(private readonly InputMinimizer $inputMinimizer) {}

    public function beginTrial(string $trialId): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new LogicException('Xiam evaluation observation is available only in local and testing environments.');
        }

        $this->trialId = substr($this->inputMinimizer->minimize($trialId), 0, 128);
        $this->records = [];
        $this->recordBytes = 0;
    }

    /**
     * Record one projected tool call while a local trial is active.
     *
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $safeProposal
     * @param  class-string<\\Throwable>|null  $exceptionClass
     */
    public function recordToolCall(
        string $runId,
        string $capabilityId,
        array $inputSchema,
        array $input,
        bool $ok,
        string $status,
        mixed $safeData,
        ?array $safeProposal = null,
        ?string $exceptionClass = null,
        ?string $stableCode = null,
    ): void {
        if ($this->trialId === null) {
            return;
        }

        if (count($this->records) >= self::MAX_RECORDS) {
            return;
        }

        $record = [
            'trial_id' => $this->trialId,
            'run_id' => $this->boundedString($runId, 128),
            'capability_id' => $this->boundedString($capabilityId, 128),
            'input' => $this->minimizeBySchema($input, $inputSchema),
            'ok' => $ok,
            'status' => $this->stableStatus($status),
            'safe_data' => $this->minimizeProjected($safeData),
            'safe_proposal' => $this->minimizeProjected($safeProposal),
        ];

        if (! $ok) {
            $record['failure'] = [
                'type' => $exceptionClass === null ? null : $this->boundedString($exceptionClass, 160),
                'code' => $this->allowlistedCode($stableCode),
            ];
        }

        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($encoded)) {
            return;
        }

        $size = strlen($encoded);
        if ($this->recordBytes + $size > self::MAX_TRIAL_BYTES) {
            return;
        }

        $this->records[] = $record;
        $this->recordBytes += $size;
    }

    /** @return list<array<string, mixed>> */
    public function snapshot(): array
    {
        return $this->records;
    }

    /** @return list<array<string, mixed>> */
    public function endTrial(): array
    {
        $snapshot = $this->records;
        $this->records = [];
        $this->trialId = null;
        $this->recordBytes = 0;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function minimizeBySchema(array $input, array $schema): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $minimized = [];

        foreach ($properties as $key => $fieldSchema) {
            if (! is_string($key) || $this->isSensitiveKey($key) || ! array_key_exists($key, $input)) {
                continue;
            }

            $value = $this->minimizeValue($input[$key], is_array($fieldSchema) ? $fieldSchema : [], 0);
            if ($value !== self::OMIT) {
                $minimized[$key] = $value;
            }
        }

        return $minimized;
    }

    private const OMIT = "\0xiam-eval-omit\0";

    /**
     * @param  array<string, mixed>  $schema
     */
    private function minimizeValue(mixed $value, array $schema, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH || $value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $depth > self::MAX_DEPTH ? self::OMIT : $value;
        }
        if (is_string($value)) {
            return $this->boundedString($this->inputMinimizer->minimize($value), self::MAX_STRING_BYTES);
        }
        if (! is_array($value)) {
            return self::OMIT;
        }

        if (array_is_list($value)) {
            $itemsSchema = is_array($schema['items'] ?? null) ? $schema['items'] : [];
            $items = [];
            foreach (array_slice($value, 0, self::MAX_LIST_ITEMS) as $item) {
                $minimized = $this->minimizeValue($item, $itemsSchema, $depth + 1);
                if ($minimized !== self::OMIT) {
                    $items[] = $minimized;
                }
            }

            return $items;
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $object = [];
        foreach ($properties as $key => $propertySchema) {
            if (! is_string($key) || $this->isSensitiveKey($key) || ! array_key_exists($key, $value)) {
                continue;
            }
            $minimized = $this->minimizeValue($value[$key], is_array($propertySchema) ? $propertySchema : [], $depth + 1);
            if ($minimized !== self::OMIT) {
                $object[$key] = $minimized;
            }
        }

        return $object;
    }

    private function minimizeProjected(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->boundedString($this->inputMinimizer->minimize($value), self::MAX_STRING_BYTES);
        }
        if (! is_array($value)) {
            return is_bool($value) || is_int($value) || is_float($value) || $value === null ? $value : null;
        }
        if (array_is_list($value)) {
            $items = [];
            foreach (array_slice($value, 0, self::MAX_LIST_ITEMS) as $item) {
                $items[] = $this->minimizeProjectedDepth($item, 1);
            }

            return $items;
        }

        $projected = [];
        foreach (array_slice($value, 0, self::MAX_LIST_ITEMS, true) as $key => $item) {
            if (! is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }
            $projected[$key] = $this->minimizeProjectedDepth($item, 1);
        }

        return $projected;
    }

    private function minimizeProjectedDepth(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }
        if (is_string($value)) {
            return $this->boundedString($this->inputMinimizer->minimize($value), self::MAX_STRING_BYTES);
        }
        if (! is_array($value)) {
            return is_bool($value) || is_int($value) || is_float($value) || $value === null ? $value : null;
        }

        $projected = [];
        foreach (array_slice($value, 0, self::MAX_LIST_ITEMS, true) as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }
            $projected[$key] = $this->minimizeProjectedDepth($item, $depth + 1);
        }

        return $projected;
    }

    private function stableStatus(string $status): string
    {
        return in_array($status, ['ok', 'denied', 'invalid', 'unsupported', 'failed', 'projection_refused'], true)
            ? $status
            : 'failed';
    }

    private function allowlistedCode(?string $code): ?string
    {
        return in_array($code, [
            'credential_input_refused',
            'tool_result_budget_exhausted',
            'assistant_tool_not_registered',
            'capability_missing',
            'capability_denied',
            'capability_invalid',
            'projection_refused',
        ], true) ? $code : null;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        $sensitiveTokens = [
            'access', 'address', 'api', 'auth', 'birth', 'credential', 'dob', 'email', 'execute', 'first',
            'key', 'last', 'lrn', 'name', 'password', 'phone', 'pwd', 'secret', 'student', 'token',
        ];

        if (str_starts_with($normalized, '_')) {
            return true;
        }

        foreach (preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (in_array($token, $sensitiveTokens, true)) {
                return true;
            }
        }

        return in_array($normalized, [
            'actor_id', 'confirmation_token', 'idempotency_key', 'institution_id', 'reference_code', 'tool_call_id',
        ], true);
    }

    private function boundedString(string $value, int $maxBytes): string
    {
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        return substr($value, 0, $maxBytes);
    }
}
