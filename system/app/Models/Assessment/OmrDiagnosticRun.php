<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Diagnostic runs retain their material context for the life of the run.
 * Lifecycle transitions are guarded separately from those immutable fields.
 *
 * @property array<string, mixed>|null $expected_worker_identity_json
 * @property array<string, mixed>|null $acceptance_plan_json
 * @property array<string, mixed>|null $encrypted_acceptance_plan_snapshot
 * @property string|null $worker_commit
 * @property string|null $manifest_sha256
 * @property string|null $test_kit_manifest_sha256
 */
class OmrDiagnosticRun extends Model
{
    public const PURPOSE_MATERIAL_DIAGNOSTIC = 'material_diagnostic';

    public const PURPOSE_KIT_ACCEPTANCE = 'kit_acceptance';

    public const PURPOSE_MOCK_REHEARSAL = 'mock_rehearsal';

    /**
     * @var list<string>
     */
    public const DIAGNOSTIC_PURPOSES = [
        self::PURPOSE_MATERIAL_DIAGNOSTIC,
        self::PURPOSE_KIT_ACCEPTANCE,
        self::PURPOSE_MOCK_REHEARSAL,
    ];

    public const EXECUTION_KIND_DIAGNOSTIC = 'diagnostic';

    /**
     * @var list<string>
     */
    public const EXECUTION_KINDS = [
        self::EXECUTION_KIND_DIAGNOSTIC,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PROCESSING,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public const PHASE_CAPTURE = 'capture';

    public const PHASE_PROCESSING = 'processing';

    public const PHASE_EVIDENCE = 'evidence';

    public const PHASE_REVIEW = 'review';

    public const PHASE_RECOVERY = 'recovery';

    public const PHASE_COMPLETE = 'complete';

    /**
     * @var list<string>
     */
    public const PHASES = [
        self::PHASE_CAPTURE,
        self::PHASE_PROCESSING,
        self::PHASE_EVIDENCE,
        self::PHASE_REVIEW,
        self::PHASE_RECOVERY,
        self::PHASE_COMPLETE,
    ];

    /**
     * @var list<string>
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    /**
     * @var list<string>
     */
    private const ACCEPTANCE_CONTEXT_FIELDS = [
        'test_kit_id',
        'test_kit_version_id',
        'acceptance_plan_id',
        'booklet_set_id',
        'manifest_sha256',
        'test_instrument_version_id',
        'profile_content_sha256',
        'acceptance_protocol_schema_version',
        'acceptance_protocol_sha256',
        'acceptance_plan_schema_version',
        'acceptance_plan_sha256',
        'scoring_kernel_identity',
        'expected_worker_identity_json',
        'acceptance_plan_json',
    ];

    /**
     * @var list<string>
     */
    private const ACCEPTANCE_SEAL_FIELDS = [
        'sealed_evidence_revision',
        'sealed_evidence_vector_sha256',
        'sealed_at',
    ];

    protected $table = 'omr_diagnostic_runs';

    protected $attributes = [
        'execution_kind' => self::EXECUTION_KIND_DIAGNOSTIC,
        'status' => self::STATUS_OPEN,
        'phase' => self::PHASE_CAPTURE,
        'diagnostic_purpose' => self::PURPOSE_MATERIAL_DIAGNOSTIC,
        'revision' => 0,
    ];

    protected $fillable = [
        'public_id',
        'execution_kind',
        'institution_id',
        'academic_cycle_id',
        'template_version_id',
        'booklet_version_id',
        'declared_set_code',
        'test_kit_id',
        'test_kit_version_id',
        'acceptance_plan_id',
        'booklet_set_id',
        'manifest_sha256',
        'test_kit_manifest_sha256',
        'test_instrument_version_id',
        'profile_content_sha256',
        'acceptance_protocol_schema_version',
        'acceptance_protocol_sha256',
        'acceptance_plan_schema_version',
        'acceptance_plan_sha256',
        'scoring_kernel_identity',
        'worker_commit',
        'expected_worker_identity_json',
        'acceptance_plan_json',
        'encrypted_acceptance_plan_snapshot',
        'diagnostic_purpose',
        'mock_manifest_json',
        'mock_manifest_schema_version',
        'mock_manifest_sha256',
        'sealed_evidence_revision',
        'sealed_evidence_vector_sha256',
        'sealed_at',
        'status',
        'phase',
        'revision',
        'created_by_user_id',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $run): void {
            if ((string) $run->public_id === '') {
                $run->public_id = (string) Str::uuid();
            }

            if ($run->execution_kind !== self::EXECUTION_KIND_DIAGNOSTIC) {
                throw new InvalidArgumentException('omr_diagnostic_run_execution_kind_invalid');
            }

            if (! in_array($run->declared_set_code, OmrBookletSet::SET_CODES, true)) {
                throw new InvalidArgumentException('omr_diagnostic_run_set_code_invalid');
            }
            $run->assertAcceptanceContextCoherent();
            if (! in_array($run->diagnostic_purpose, self::DIAGNOSTIC_PURPOSES, true)) {
                throw new InvalidArgumentException('omr_diagnostic_run_purpose_invalid');
            }
        });

        /**
         * Material context is fixed when a diagnostic run is created. Lifecycle
         * fields can advance, but they cannot move the run to another scope.
         */
        static::updating(static function (self $run): void {
            foreach ([
                'public_id',
                'execution_kind',
                'institution_id',
                'academic_cycle_id',
                'template_version_id',
                'booklet_version_id',
                'declared_set_code',
                'created_by_user_id',
                'created_at',
                'diagnostic_purpose',
                'mock_manifest_json',
                'mock_manifest_schema_version',
                'mock_manifest_sha256',
                ...self::ACCEPTANCE_CONTEXT_FIELDS,
            ] as $field) {
                if ($run->isDirty($field)) {
                    throw new RuntimeException('omr_diagnostic_run_material_context_immutable');
                }
            }

            if ($run->getRawOriginal('sealed_at') !== null) {
                foreach (self::ACCEPTANCE_SEAL_FIELDS as $field) {
                    if ($run->isDirty($field)) {
                        throw new RuntimeException('omr_diagnostic_run_evidence_seal_immutable');
                    }
                }
            }

            $run->assertAcceptanceContextCoherent();

            $originalRevision = (int) ($run->getRawOriginal('revision') ?? 0);

            if ((int) $run->revision < $originalRevision) {
                throw new RuntimeException('omr_diagnostic_run_revision_not_monotonic');
            }
        });
    }

    /**
     * Whether this run was created with a frozen Test Kit acceptance contract.
     */
    public function hasAcceptanceContext(): bool
    {
        return $this->diagnostic_purpose === self::PURPOSE_KIT_ACCEPTANCE;
    }

    /**
     * Whether this run was created for an isolated mock rehearsal.
     */
    public function hasMockContext(): bool
    {
        return $this->diagnostic_purpose === self::PURPOSE_MOCK_REHEARSAL;
    }

    private function assertAcceptanceContextCoherent(): void
    {
        $purpose = $this->diagnostic_purpose;
        if (! in_array($purpose, self::DIAGNOSTIC_PURPOSES, true)) {
            throw new InvalidArgumentException('omr_diagnostic_run_purpose_invalid');
        }

        $hasAcceptanceContext = collect(self::ACCEPTANCE_CONTEXT_FIELDS)
            ->contains(fn (string $field): bool => $this->getAttribute($field) !== null);
        $hasMockContext = $this->mock_manifest_json !== null
            || $this->mock_manifest_schema_version !== null
            || $this->mock_manifest_sha256 !== null;
        $hasSeal = collect(self::ACCEPTANCE_SEAL_FIELDS)
            ->contains(fn (string $field): bool => $this->getAttribute($field) !== null);

        if ($purpose === self::PURPOSE_MATERIAL_DIAGNOSTIC) {
            if ($hasAcceptanceContext || $hasMockContext || $hasSeal) {
                throw new InvalidArgumentException('omr_diagnostic_run_material_context_invalid');
            }

            return;
        }

        if ($purpose === self::PURPOSE_MOCK_REHEARSAL) {
            if ($this->test_kit_id === null || $this->test_kit_version_id === null
                || $this->booklet_set_id === null || $this->manifest_sha256 === null
                || $this->test_instrument_version_id === null || $this->profile_content_sha256 === null
                || $this->scoring_kernel_identity === null || ! is_array($this->expected_worker_identity_json)
                || $this->expected_worker_identity_json === [] || $this->acceptance_plan_id !== null
                || $this->acceptance_protocol_schema_version !== null || $this->acceptance_protocol_sha256 !== null
                || $this->acceptance_plan_schema_version !== null || $this->acceptance_plan_sha256 !== null
                || $this->acceptance_plan_json !== null || ! is_array($this->mock_manifest_json)
                || $this->mock_manifest_json === [] || ! is_string($this->mock_manifest_schema_version)
                || trim($this->mock_manifest_schema_version) === ''
                || ! is_string($this->mock_manifest_sha256)
                || ! preg_match('/^[a-f0-9]{64}$/D', $this->mock_manifest_sha256) || $hasSeal) {
                throw new InvalidArgumentException('omr_diagnostic_run_mock_context_invalid');
            }
            foreach (['manifest_sha256', 'profile_content_sha256'] as $field) {
                if (! is_string($this->getAttribute($field))
                    || ! preg_match('/^[a-f0-9]{64}$/D', $this->getAttribute($field))) {
                    throw new InvalidArgumentException('omr_diagnostic_run_mock_hash_invalid');
                }
            }

            return;
        }

        if (! $hasAcceptanceContext || $hasMockContext) {
            throw new InvalidArgumentException('omr_diagnostic_run_acceptance_context_incomplete');
        }
        foreach (self::ACCEPTANCE_CONTEXT_FIELDS as $field) {
            if ($this->getAttribute($field) === null) {
                throw new InvalidArgumentException('omr_diagnostic_run_acceptance_context_incomplete');
            }
        }
        if ($this->declared_set_code !== 'A'
            || ! is_array($this->expected_worker_identity_json)
            || $this->expected_worker_identity_json === []
            || ! is_array($this->acceptance_plan_json)
            || ! is_array($this->acceptance_plan_json['cases'] ?? null)
            || $this->acceptance_plan_json['cases'] === []) {
            throw new InvalidArgumentException('omr_diagnostic_run_acceptance_context_invalid');
        }
        foreach (['manifest_sha256', 'profile_content_sha256', 'acceptance_protocol_sha256', 'acceptance_plan_sha256'] as $field) {
            if (! is_string($this->getAttribute($field))
                || ! preg_match('/^[a-f0-9]{64}$/D', $this->getAttribute($field))) {
                throw new InvalidArgumentException('omr_diagnostic_run_acceptance_hash_invalid');
            }
        }
        $sealValues = array_map(fn (string $field): mixed => $this->getAttribute($field), self::ACCEPTANCE_SEAL_FIELDS);
        if (count(array_filter($sealValues, static fn (mixed $value): bool => $value !== null)) > 0
            && ($this->sealed_evidence_revision === null
                || ! is_string($this->sealed_evidence_vector_sha256)
                || ! preg_match('/^[a-f0-9]{64}$/D', $this->sealed_evidence_vector_sha256)
                || $this->sealed_at === null)) {
            throw new InvalidArgumentException('omr_diagnostic_run_evidence_seal_incomplete');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_id' => 'string',
            'institution_id' => 'integer',
            'academic_cycle_id' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'test_kit_id' => 'integer',
            'test_kit_version_id' => 'integer',
            'acceptance_plan_id' => 'integer',
            'booklet_set_id' => 'integer',
            'manifest_sha256' => 'string',
            'test_kit_manifest_sha256' => 'string',
            'test_instrument_version_id' => 'integer',
            'profile_content_sha256' => 'string',
            'acceptance_protocol_schema_version' => 'string',
            'acceptance_protocol_sha256' => 'string',
            'acceptance_plan_schema_version' => 'string',
            'acceptance_plan_sha256' => 'string',
            'scoring_kernel_identity' => 'string',
            'worker_commit' => 'string',
            'diagnostic_purpose' => 'string',
            'mock_manifest_json' => 'encrypted:array',
            'mock_manifest_schema_version' => 'string',
            'mock_manifest_sha256' => 'string',
            'expected_worker_identity_json' => 'array',
            'acceptance_plan_json' => 'encrypted:array',
            'encrypted_acceptance_plan_snapshot' => 'encrypted:array',
            'sealed_evidence_revision' => 'integer',
            'sealed_evidence_vector_sha256' => 'string',
            'sealed_at' => 'datetime',
            'revision' => 'integer',
            'created_by_user_id' => 'integer',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /**
     * @return BelongsTo<OmrAnswerSheetTemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    /**
     * @return BelongsTo<OmrQuestionBookletVersion, $this>
     */
    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    /**
     * @return BelongsTo<TestKit, $this>
     */
    public function testKit(): BelongsTo
    {
        return $this->belongsTo(TestKit::class, 'test_kit_id');
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /**
     * @return BelongsTo<TestKitAcceptancePlanRecord, $this>
     */
    public function acceptancePlan(): BelongsTo
    {
        return $this->belongsTo(TestKitAcceptancePlanRecord::class, 'acceptance_plan_id');
    }

    public function getTestKitManifestSha256Attribute(): ?string
    {
        return $this->manifest_sha256 ?? ($this->attributes['manifest_sha256'] ?? null);
    }

    public function setTestKitManifestSha256Attribute(?string $value): void
    {
        $this->attributes['manifest_sha256'] = $value;
    }

    public function getWorkerCommitAttribute(): ?string
    {
        if (isset($this->attributes['worker_commit'])) {
            return (string) $this->attributes['worker_commit'];
        }

        $worker = $this->expected_worker_identity_json;

        return is_array($worker) && isset($worker['engine_commit'])
            ? (string) $worker['engine_commit']
            : null;
    }

    public function setWorkerCommitAttribute(?string $value): void
    {
        if ($value !== null) {
            $worker = is_array($this->expected_worker_identity_json)
                ? $this->expected_worker_identity_json
                : [];
            $worker['engine_commit'] = $value;
            $this->expected_worker_identity_json = $worker;
        }
    }

    public function getEncryptedAcceptancePlanSnapshotAttribute(): mixed
    {
        return $this->acceptance_plan_json;
    }

    public function setEncryptedAcceptancePlanSnapshotAttribute(mixed $value): void
    {
        if ($value !== null) {
            $this->acceptance_plan_json = $value;
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<OmrDiagnosticCapture, $this>
     */
    public function captures(): HasMany
    {
        return $this->hasMany(OmrDiagnosticCapture::class, 'diagnostic_run_id');
    }
}
