<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property int $id
 * @property int $diagnostic_run_id
 * @property int|null $input_asset_id
 * @property string|null $input_asset_hash
 * @property array<string, mixed>|null $evidence_json
 * @property array<string, mixed>|null $detected_answers_json
 * @property array<string, mixed>|null $effective_answers_json
 * @property array<string, mixed>|null $projected_score_json
 * @property string|null $evidence_hash
 * @property string $status
 * @property int $version
 */
class OmrDiagnosticCapture extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_REVIEWED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * @var list<string>
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $table = 'omr_diagnostic_captures';

    protected $attributes = [
        'status' => self::STATUS_RECEIVED,
        'version' => 0,
    ];

    protected $fillable = [
        'diagnostic_run_id',
        'client_capture_id',
        'status',
        'version',
        'input_asset_id',
        'input_asset_hash',
        'aligned_asset_id',
        'aligned_asset_hash',
        'overlay_asset_id',
        'overlay_asset_hash',
        'evidence_json',
        'evidence_hash',
        'detected_answers_json',
        'effective_answers_json',
        'projected_score_json',
        'failure_code',
        'failure_detail',
        'created_by_user_id',
        'captured_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $capture): void {
            if ($capture->client_capture_id === null || $capture->client_capture_id === '') {
                $capture->client_capture_id = (string) Str::uuid();
            }
        });

        static::updating(static function (self $capture): void {
            foreach ([
                'diagnostic_run_id',
                'client_capture_id',
                'input_asset_id',
                'input_asset_hash',
                'created_by_user_id',
                'captured_at',
                'created_at',
            ] as $field) {
                if ($capture->isDirty($field)) {
                    throw new RuntimeException('omr_diagnostic_capture_input_immutable');
                }
            }

            $originalVersion = (int) ($capture->getRawOriginal('version') ?? 0);

            if ((int) $capture->version < $originalVersion) {
                throw new RuntimeException('omr_diagnostic_capture_version_not_monotonic');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnostic_run_id' => 'integer',
            'client_capture_id' => 'string',
            'version' => 'integer',
            'input_asset_id' => 'integer',
            'aligned_asset_id' => 'integer',
            'overlay_asset_id' => 'integer',
            'evidence_json' => 'array',
            'detected_answers_json' => 'array',
            'effective_answers_json' => 'array',
            'projected_score_json' => 'array',
            'created_by_user_id' => 'integer',
            'captured_at' => 'datetime',
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
     * @return BelongsTo<OmrDiagnosticRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticRun::class, 'diagnostic_run_id');
    }

    /**
     * @return BelongsTo<OmrTemplateAsset, $this>
     */
    public function inputAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'input_asset_id');
    }

    /**
     * @return BelongsTo<OmrTemplateAsset, $this>
     */
    public function alignedAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'aligned_asset_id');
    }

    /**
     * @return BelongsTo<OmrTemplateAsset, $this>
     */
    public function overlayAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'overlay_asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<OmrDiagnosticItemEvidence, $this>
     */
    public function evidenceItems(): HasMany
    {
        return $this->hasMany(OmrDiagnosticItemEvidence::class, 'diagnostic_capture_id');
    }
}
