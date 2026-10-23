<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Physical challenge pack bound to one run attempt (OpenSpec 35.21).
 * Sheet/guide assets are real FK references into the private asset store.
 * Challenge creation/rendering lands in Cut F; this model and its table
 * are the schema contract for that cut.
 */
class OmrPhysicalChallenge extends Model
{
    private static int $preCaptureRegenerationDepth = 0;

    private static int $diagnosticPersistenceDepth = 0;

    protected static function booted(): void
    {
        static::updating(static function (self $challenge): void {
            $immutable = [
                'run_id', 'attempt', 'method', 'mode', 'challenge_code',
                'target_kind', 'target_id', 'target_version', 'target_hash',
                'plan_version', 'plan_seed', 'plan_hash', 'expected_vector_hash',
                'renderer_version', 'renderer_hash', 'marker_profile_id',
                'marker_profile_version', 'marker_profile_hash',
            ];
            foreach ($immutable as $field) {
                if ($challenge->isDirty($field)) {
                    throw new RuntimeException('physical_challenge_immutable');
                }
            }

            $regenerable = [
                'sheet_asset_id', 'sheet_asset_hash', 'guide_asset_id',
                'guide_asset_hash', 'guide_json', 'qr_payload_hash', 'print_metadata',
                'page_manifest', 'page_manifest_hash',
                'scenario_manifest', 'scenario_manifest_hash', 'coverage_report',
                'coverage_hash', 'renderer_identity', 'profile_identity',
                'geometry_identity', 'source_artifact_hashes',
            ];
            $diagnosticMutable = [
                'transformed_artifact_hashes', 'scenario_outcomes',
            ];
            foreach (array_keys($challenge->getDirty()) as $field) {
                if (! in_array($field, [...$regenerable, ...$diagnosticMutable, 'status', 'updated_at'], true)) {
                    throw new RuntimeException('physical_challenge_transition_invalid');
                }
                if (in_array($field, $diagnosticMutable, true)
                    && self::$diagnosticPersistenceDepth < 1) {
                    throw new RuntimeException('physical_challenge_diagnostic_persistence_required');
                }
                if (in_array($field, $regenerable, true)
                    && self::$preCaptureRegenerationDepth < 1) {
                    throw new RuntimeException('physical_challenge_regeneration_required');
                }
            }

            if (self::$preCaptureRegenerationDepth > 0
                && array_intersect(array_keys($challenge->getDirty()), $regenerable) !== []
                && DB::table('omr_template_verification_captures')
                    ->where('challenge_id', $challenge->getKey())
                    ->exists()) {
                throw new RuntimeException('physical_challenge_evidence_exists');
            }
        });
    }

    public static function allowPreCaptureRegeneration(callable $callback): mixed
    {
        self::$preCaptureRegenerationDepth++;
        try {
            return $callback();
        } finally {
            self::$preCaptureRegenerationDepth--;
        }
    }

    public static function allowDiagnosticPersistence(callable $callback): mixed
    {
        self::$diagnosticPersistenceDepth++;
        try {
            return $callback();
        } finally {
            self::$diagnosticPersistenceDepth--;
        }
    }

    public const MODE_QUICK = 'quick';

    public const MODE_FULL = 'full';

    public const MODE_QUICK_REHEARSAL = 'quick_rehearsal';

    public const MODE_FULL_QUALIFICATION = 'full_qualification';

    public const METHOD_PRE_SHADED = 'pre_shaded';

    public const METHOD_HAND_SHADED = 'hand_shaded';

    public const METHODS = [self::METHOD_PRE_SHADED, self::METHOD_HAND_SHADED];

    public const STATUS_CREATED = 'created';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_AWAITING_CAPTURE = 'awaiting_capture';

    public const STATUS_ANALYZING_CAPTURE = 'analyzing_capture';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'omr_physical_challenges';

    protected $attributes = [
        'method' => self::METHOD_PRE_SHADED,
        'plan_version' => 'v1',
        'status' => self::STATUS_CREATED,
    ];

    protected $fillable = [
        'run_id', 'attempt', 'method', 'mode', 'challenge_code',
        'target_kind', 'target_id', 'target_version', 'target_hash',
        'plan_version', 'plan_seed', 'plan_hash', 'expected_vector_hash',
        'renderer_version', 'renderer_hash', 'marker_profile_id',
        'marker_profile_version', 'marker_profile_hash', 'page_manifest',
        'page_manifest_hash', 'scenario_manifest', 'scenario_manifest_hash',
        'coverage_report', 'coverage_hash', 'renderer_identity',
        'profile_identity', 'geometry_identity', 'source_artifact_hashes',
        'transformed_artifact_hashes', 'scenario_outcomes', 'sheet_asset_id',
        'sheet_asset_hash', 'guide_asset_id', 'guide_asset_hash', 'guide_json',
        'qr_payload_hash', 'print_metadata', 'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'attempt' => 'integer',
            'target_id' => 'integer',
            'marker_profile_id' => 'integer',
            'marker_profile_version' => 'integer',
            'sheet_asset_id' => 'integer',
            'guide_asset_id' => 'integer',
            'page_manifest' => 'array',
            'scenario_manifest' => 'array',
            'coverage_report' => 'array',
            'renderer_identity' => 'array',
            'profile_identity' => 'array',
            'source_artifact_hashes' => 'array',
            'transformed_artifact_hashes' => 'array',
            'scenario_outcomes' => 'array',
            'guide_json' => 'array',
            'print_metadata' => 'array',
            'created_by_user_id' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationRun::class, 'run_id');
    }

    public function markerProfile(): BelongsTo
    {
        return $this->belongsTo(OmrMarkerProfile::class, 'marker_profile_id');
    }

    public function sheetAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'sheet_asset_id');
    }

    public function guideAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'guide_asset_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationCapture::class, 'challenge_id');
    }

    public function scopeForMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', $method);
    }

    public function isPreShaded(): bool
    {
        return $this->method === self::METHOD_PRE_SHADED;
    }

    public function isHandShaded(): bool
    {
        return $this->method === self::METHOD_HAND_SHADED;
    }
}
