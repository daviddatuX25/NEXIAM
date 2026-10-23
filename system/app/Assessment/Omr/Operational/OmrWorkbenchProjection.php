<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use InvalidArgumentException;

use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_null;
use function is_resource;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strtolower;
use function trim;

/**
 * Server-authored, answer-key-blind state for one OMR workbench run.
 *
 * The projection intentionally contains only scalar/array data. Domain models,
 * providers, and expected vectors stay behind the authority adapter boundary.
 */
final readonly class OmrWorkbenchProjection
{
    public const PHASE_CAPTURE = 'capture';

    public const PHASE_PREFLIGHT = 'preflight';

    public const PHASE_CONTEXT = 'context';

    public const PHASE_PROCESSING = 'processing';

    public const PHASE_EVIDENCE = 'evidence';

    public const PHASE_REVIEW = 'review';

    public const PHASE_RECOVERY = 'recovery';

    public const PHASE_COMPLETION = 'completion';

    public const PHASE_COMPLETE = 'complete';

    public const PHASE_TERMINAL = 'terminal';

    /** @var list<string> */
    public const PHASES = [
        self::PHASE_CAPTURE,
        self::PHASE_PREFLIGHT,
        self::PHASE_CONTEXT,
        self::PHASE_PROCESSING,
        self::PHASE_EVIDENCE,
        self::PHASE_REVIEW,
        self::PHASE_RECOVERY,
        self::PHASE_COMPLETION,
        self::PHASE_COMPLETE,
        self::PHASE_TERMINAL,
    ];

    /**
     * Keys which could disclose an answer key or a comparison-only expected
     * vector. The check is applied recursively to every projected section.
     *
     * @var list<string>
     */
    private const FORBIDDEN_KEY_PARTS = [
        'set',
        'key',
        'qualification',
        'score',
        'expected',
        'correct',
    ];

    public readonly string $workbenchId;

    public readonly OmrExecutionKind $executionKind;

    public readonly int $revision;

    public readonly string $phase;

    /** @var array<array-key, mixed> */
    public readonly array $context;

    /** @var array<array-key, mixed> */
    public readonly array $sheets;

    /** @var array<array-key, mixed> */
    public readonly array $evidence;

    /** @var array<array-key, mixed> */
    public readonly array $recovery;

    /** @var array<array-key, mixed>|null */
    public readonly ?array $completion;

    /** @var list<OmrWorkbenchCommand> */
    public readonly array $commands;

    /**
     * @param  array<string|int, mixed>  $context
     * @param  array<string|int, mixed>  $sheets
     * @param  array<string|int, mixed>  $evidence
     * @param  array<string|int, mixed>  $recovery
     * @param  array<string|int, mixed>|null  $completion
     * @param  list<OmrWorkbenchCommand|string>  $commands
     */
    public function __construct(
        string $workbenchId,
        OmrExecutionKind $executionKind,
        int $revision,
        string $phase,
        array $context = [],
        array $sheets = [],
        array $evidence = [],
        array $recovery = [],
        ?array $completion = null,
        array $commands = [],
    ) {
        $this->workbenchId = self::identity($workbenchId);
        $this->executionKind = $executionKind;
        self::assertIdentityKind($this->workbenchId, $executionKind);
        $this->revision = self::revision($revision);
        $this->phase = self::phase($phase);
        $this->context = self::safeArray($context, 'context');
        $this->assertContextKind($this->context, $executionKind);
        $this->sheets = self::safeArray($sheets, 'sheets');
        $this->evidence = self::safeArray($evidence, 'evidence');
        $this->recovery = self::safeArray($recovery, 'recovery');
        $this->completion = $completion === null
            ? null
            : self::safeArray($completion, 'completion');
        $this->commands = self::commands($commands, $executionKind);
    }

    /**
     * Hydrate a projection from its own safe representation.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSafeArray(array $payload): self
    {
        $workbenchId = $payload['workbench_id'] ?? null;
        $executionKind = $payload['execution_kind'] ?? null;
        $revision = $payload['revision'] ?? null;
        $phase = $payload['phase'] ?? null;

        if (! is_string($workbenchId)
            || ! is_string($executionKind)
            || ! is_int($revision)
            || ! is_string($phase)) {
            throw new InvalidArgumentException('OMR workbench projection identity is malformed.');
        }

        $kind = OmrExecutionKind::tryFrom($executionKind);
        if ($kind === null) {
            throw new InvalidArgumentException('OMR workbench execution kind is unsupported.');
        }

        $context = $payload['context'] ?? [];
        $sheets = $payload['sheets'] ?? [];
        $evidence = $payload['evidence'] ?? [];
        $recovery = $payload['recovery'] ?? [];
        $completion = $payload['completion'] ?? null;
        $commands = $payload['commands'] ?? [];

        if (! is_array($context)
            || ! is_array($sheets)
            || ! is_array($evidence)
            || ! is_array($recovery)
            || ($completion !== null && ! is_array($completion))
            || ! is_array($commands)) {
            throw new InvalidArgumentException('OMR workbench projection sections are malformed.');
        }

        return new self(
            workbenchId: $workbenchId,
            executionKind: $kind,
            revision: $revision,
            phase: $phase,
            context: $context,
            sheets: $sheets,
            evidence: $evidence,
            recovery: $recovery,
            completion: $completion,
            commands: $commands,
        );
    }

    /**
     * Return the stable browser-safe representation.
     *
     * @return array{
     *     workbench_id: string,
     *     execution_kind: string,
     *     revision: int,
     *     phase: string,
     *     context: array<array-key, mixed>,
     *     sheets: array<array-key, mixed>,
     *     evidence: array<array-key, mixed>,
     *     recovery: array<array-key, mixed>,
     *     completion: array<array-key, mixed>|null,
     *     commands: list<string>
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'workbench_id' => $this->workbenchId,
            'execution_kind' => $this->executionKind->value,
            'revision' => $this->revision,
            'phase' => $this->phase,
            'context' => $this->context,
            'sheets' => $this->sheets,
            'evidence' => $this->evidence,
            'recovery' => $this->recovery,
            'completion' => $this->completion,
            'commands' => array_values(array_map(
                static fn (OmrWorkbenchCommand $command): string => $command->value,
                $this->commands,
            )),
        ];
    }

    public function hasCommand(OmrWorkbenchCommand $command): bool
    {
        return in_array($command, $this->commands, true);
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    private static function safeArray(array $values, string $section): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (! is_int($key) && ! is_string($key)) {
                throw new InvalidArgumentException("OMR workbench {$section} contains an invalid key.");
            }

            if (is_string($key)) {
                if ($key === '' || trim($key) !== $key) {
                    throw new InvalidArgumentException("OMR workbench {$section} contains an invalid field.");
                }
                $retakeProtocolIdentity = (str_ends_with($section, '.capture_retake')
                    && in_array($key, [
                        'expected_capture_version', 'expected_revision', 'pending_capture_id',
                        'pending_capture_revision', 'target_key',
                    ], true))
                    || (str_ends_with($section, '.capture_retake.target')
                        && in_array($key, [
                            'workbench_id', 'client_capture_id', 'page_no', 'target_revision',
                            'instance_id', 'sheet_id', 'target_key',
                        ], true));
                $protocolIdentity = $retakeProtocolIdentity
                    || (in_array($key, ['target_key', 'targetKey', 'acceptedTargetKey', 'accepted_target_key', 'redo_accepted_target_key', 'identity_mode', 'supplier_stock_allocation_id'], true)
                        && ($section === 'context.capture_target' || $section === 'context.capture_receipt'
                            || $section === 'context.capture_receipt.target'
                            || in_array($section, ['context.capture_actions.next_page', 'context.capture_actions.next_set', 'context.capture_actions.redo_last'], true)));
                $nextSetAction = $section === 'context.capture_actions' && $key === 'next_set';
                $assetDescriptors = $key === 'assets' && self::isAssetDescriptorSection($section);
                if (! $protocolIdentity && ! $nextSetAction && ! $assetDescriptors && self::isForbiddenKey($key)) {
                    continue;
                }
            } else {
                $assetDescriptors = false;
            }

            $normalized[$key] = $assetDescriptors
                ? self::safeAssetDescriptors($value)
                : self::safeValue($value, $section.'.'.(string) $key);
        }

        return $normalized;
    }

    private static function isAssetDescriptorSection(string $section): bool
    {
        return preg_match('/\Asheets\.\d+\z/D', $section) === 1
            || preg_match('/\Aevidence\.captures\.\d+\.evidence\z/D', $section) === 1;
    }

    /**
     * Keep only identifiers and display metadata for the existing authenticated
     * asset route. Filesystem paths and caller-supplied URLs never cross this
     * projection boundary.
     *
     * @return array<string, array<string, int|string>>
     */
    private static function safeAssetDescriptors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $allowedKinds = ['input', 'captured', 'aligned', 'overlay'];
        $descriptors = [];
        foreach ($value as $kind => $descriptor) {
            if (! is_string($kind) || ! in_array($kind, $allowedKinds, true) || ! is_array($descriptor)) {
                continue;
            }

            $safeDescriptor = [];
            foreach (['id', 'hash', 'sha256', 'mime', 'width', 'height'] as $field) {
                $fieldValue = $descriptor[$field] ?? null;
                if ($field === 'id' && is_int($fieldValue) && $fieldValue > 0) {
                    $safeDescriptor[$field] = $fieldValue;
                } elseif (in_array($field, ['width', 'height'], true) && is_int($fieldValue) && $fieldValue > 0) {
                    $safeDescriptor[$field] = $fieldValue;
                } elseif (in_array($field, ['hash', 'sha256'], true)
                    && is_string($fieldValue)
                    && preg_match('/\A[a-f0-9]{64}\z/iD', $fieldValue) === 1) {
                    $safeDescriptor[$field] = $fieldValue;
                } elseif ($field === 'mime'
                    && is_string($fieldValue)
                    && preg_match('/\Aimage\/[a-z0-9.+-]{1,64}\z/iD', $fieldValue) === 1) {
                    $safeDescriptor[$field] = $fieldValue;
                }
            }

            if ($safeDescriptor !== []) {
                $descriptors[$kind] = $safeDescriptor;
            }
        }

        return $descriptors;
    }

    private static function safeValue(mixed $value, string $path): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException("OMR workbench {$path} contains a non-finite number.");
            }

            return $value;
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new InvalidArgumentException("OMR workbench {$path} contains invalid text.");
            }

            return $value;
        }

        if (is_array($value)) {
            return self::safeArray($value, $path);
        }

        if (is_resource($value)) {
            throw new InvalidArgumentException("OMR workbench {$path} contains a resource.");
        }

        throw new InvalidArgumentException("OMR workbench {$path} contains an unsupported value.");
    }

    private static function assertIdentityKind(string $workbenchId, OmrExecutionKind $executionKind): void
    {
        if ($executionKind === OmrExecutionKind::Production
            && preg_match('/\Aproduction:[1-9][0-9]*:[1-9][0-9]*\z/D', $workbenchId) !== 1) {
            throw new InvalidArgumentException('A production workbench identity must include its session and batch.');
        }
    }

    private static function identity(string $workbenchId): string
    {
        $workbenchId = trim($workbenchId);

        if ($workbenchId === ''
            || strlen($workbenchId) > 128
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $workbenchId) !== 1) {
            throw new InvalidArgumentException('OMR workbench identity is invalid.');
        }

        return $workbenchId;
    }

    private static function revision(int $revision): int
    {
        if ($revision < 1) {
            throw new InvalidArgumentException('OMR workbench revision must be positive.');
        }

        return $revision;
    }

    private static function phase(string $phase): string
    {
        $phase = trim($phase);

        if (! in_array($phase, self::PHASES, true)) {
            throw new InvalidArgumentException('OMR workbench phase is unsupported.');
        }

        return $phase;
    }

    /**
     * @param  array<string|int, mixed>  $context
     */
    private function assertContextKind(array $context, OmrExecutionKind $executionKind): void
    {
        $declaredKind = $context['execution_kind'] ?? null;

        if ($declaredKind !== null
            && (! is_string($declaredKind) || $declaredKind !== $executionKind->value)) {
            throw new InvalidArgumentException('OMR workbench context execution kind does not match its projection.');
        }
    }

    /**
     * @param  list<OmrWorkbenchCommand|string>  $commands
     * @return list<OmrWorkbenchCommand>
     */
    private static function commands(array $commands, OmrExecutionKind $executionKind): array
    {
        $allowed = [];

        foreach (array_values($commands) as $command) {
            if (is_string($command)) {
                $command = OmrWorkbenchCommand::tryFrom($command);
            }

            if (! $command instanceof OmrWorkbenchCommand) {
                throw new InvalidArgumentException('OMR workbench command is unsupported.');
            }

            if (! self::commandAllowed($command, $executionKind) || in_array($command, $allowed, true)) {
                continue;
            }

            $allowed[] = $command;
        }

        return $allowed;
    }

    private static function commandAllowed(OmrWorkbenchCommand $command, OmrExecutionKind $executionKind): bool
    {
        return match ($executionKind) {
            OmrExecutionKind::Production => in_array($command, [
                OmrWorkbenchCommand::CaptureStart,
                OmrWorkbenchCommand::CaptureRetry,
                OmrWorkbenchCommand::ReviewSave,
                OmrWorkbenchCommand::ResolveApplicant,
                OmrWorkbenchCommand::ResolveDuplicate,
                OmrWorkbenchCommand::ProductionComplete,
            ], true),
            OmrExecutionKind::Diagnostic => ! in_array($command, [
                OmrWorkbenchCommand::SelectSession,
                OmrWorkbenchCommand::ProductionComplete,
            ], true),
        };
    }

    private static function isForbiddenKey(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($key));

        if (! is_string($normalized) || $normalized === '') {
            return false;
        }

        foreach (self::FORBIDDEN_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
