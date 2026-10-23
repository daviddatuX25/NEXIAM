<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property array<string, mixed>|null $check_report_json
 * @property array<string, mixed>|null $evidence_revision_vector_json
 * @property array<string, mixed>|null $confidential_evidence_references
 */
class TestKitAcceptanceReceipt extends Model
{
    public const RESULT_PASSED = 'passed';

    public const RESULT_FAILED = 'failed';

    public $timestamps = false;

    protected $hidden = [
        'confidential_evidence_references',
    ];

    protected $fillable = [
        'public_id',
        'test_kit_version_id',
        'diagnostic_run_id',
        'acceptance_plan_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_code',
        'run_revision',
        'evidence_revision_vector_json',
        'evidence_revision_vector_sha256',
        'acceptance_protocol_schema_version',
        'acceptance_protocol_sha256',
        'acceptance_plan_schema_version',
        'acceptance_plan_sha256',
        'scoring_kernel_identity',
        'worker_commit',
        'manifest_sha256',
        'result',
        'check_report_json',
        'confidential_evidence_references',
        'created_by_user_id',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new RuntimeException('test_kit_acceptance_receipts_are_append_only');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('test_kit_acceptance_receipts_are_append_only');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_kit_version_id' => 'integer',
            'acceptance_plan_id' => 'integer',
            'diagnostic_run_id' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'run_revision' => 'integer',
            'evidence_revision_vector_json' => 'array',
            'check_report_json' => 'array',
            'confidential_evidence_references' => 'encrypted:array',
            'created_by_user_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /**
     * @return BelongsTo<OmrDiagnosticRun, $this>
     */
    public function diagnosticRun(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticRun::class, 'diagnostic_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
