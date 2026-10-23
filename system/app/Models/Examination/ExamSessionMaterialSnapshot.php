<?php

namespace App\Models\Examination;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ExamSessionMaterialSnapshot extends Model
{
    public const LEGACY_BACKFILL_NOT_ATTEMPTED = 'not_attempted';

    public const LEGACY_BACKFILL_BACKFILLED = 'backfilled';

    public const LEGACY_BACKFILL_UNRESOLVED = 'unresolved';

    /**
     * @var list<string>
     */
    public const LEGACY_BACKFILL_STATUSES = [
        self::LEGACY_BACKFILL_NOT_ATTEMPTED,
        self::LEGACY_BACKFILL_BACKFILLED,
        self::LEGACY_BACKFILL_UNRESOLVED,
    ];

    protected $table = 'exam_session_material_snapshots';

    protected $fillable = [
        'exam_session_id',
        'revision',
        'is_current',
        'template_version_id',
        'booklet_version_id',
        'test_instrument_version_id',
        'test_kit_version_id',
        'manifest_sha256',
        'acceptance_receipt_id',
        'seating_arrangement_id',
        'seating_mode',
        'capacity_limit',
        'required_set_codes',
        'arrangement_lock_version',
        'arrangement_rows',
        'arrangement_columns',
        'arrangement_booklet_pattern',
        'seat_manifest_hash',
        'legacy_backfill_status',
        'legacy_exception_reason',
        'scoring_policy_identity',
        'material_hash',
        'published_by_user_id',
        'published_at',
        'superseded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'test_instrument_version_id' => 'integer',
            'is_current' => 'boolean',
            'test_kit_version_id' => 'integer',
            'manifest_sha256' => 'string',
            'acceptance_receipt_id' => 'integer',
            'capacity_limit' => 'integer',
            'required_set_codes' => 'array',
            'arrangement_lock_version' => 'integer',
            'arrangement_rows' => 'integer',
            'arrangement_columns' => 'integer',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $snapshot): void {
            throw new RuntimeException('Session material snapshots are immutable.');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('Session material snapshots are immutable.');
        });
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
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
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /**
     * @return BelongsTo<TestKitAcceptanceReceipt, $this>
     */
    public function acceptanceReceipt(): BelongsTo
    {
        return $this->belongsTo(TestKitAcceptanceReceipt::class, 'acceptance_receipt_id');
    }

    /**
     * @return BelongsTo<SeatingArrangement, $this>
     */
    public function seatingArrangement(): BelongsTo
    {
        return $this->belongsTo(SeatingArrangement::class, 'seating_arrangement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @return HasMany<ExamSessionMaterialSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(ExamSessionMaterialSet::class, 'snapshot_id');
    }

    /**
     * @return HasMany<ExamSessionSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(ExamSessionSeat::class, 'material_snapshot_id');
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }
}
