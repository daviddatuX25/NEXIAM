<?php

namespace App\Models\Assessment;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnswerSheetImportOperation extends Model
{
    use HasUuids;

    public const PHASE_QUEUED = 'queued';

    public const PHASE_CHECKING = 'checking';

    public const PHASE_NEEDS_REVIEW = 'needs_review';

    public const PHASE_READY_TO_RESTORE = 'ready_to_restore';

    public const PHASE_RESTORING = 'restoring';

    public const PHASE_COMPLETED = 'completed';

    public const PHASE_FAILED = 'failed';

    public const PHASE_CANCELLED = 'cancelled';

    public const ACTIVE_PHASES = [
        self::PHASE_QUEUED,
        self::PHASE_CHECKING,
        self::PHASE_RESTORING,
    ];

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'replacement_for_id',
        'phase',
        'revision',
        'generation',
        'review_revision',
        'parser_version',
        'original_filename',
        'archive_disk',
        'archive_path',
        'archive_mime',
        'archive_bytes',
        'archive_sha256',
        'ledger_disk',
        'ledger_path',
        'ledger_bytes',
        'ledger_sha256',
        'progress_done',
        'progress_total',
        'progress_unit',
        'report_json',
        'report_sha256',
        'error_code',
        'safe_error',
        'lease_token',
        'lease_expires_at',
        'last_heartbeat_at',
        'confirmed_by_user_id',
        'confirmed_review_revision',
        'confirmation_review_token',
        'confirmed_at',
        'result_draft_id',
        'result_receipt',
        'result_summary',
        'completed_at',
    ];

    protected $hidden = [
        'archive_disk',
        'archive_path',
        'ledger_disk',
        'ledger_path',
        'lease_token',
        'confirmation_review_token',
        'result_receipt',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'generation' => 'integer',
            'review_revision' => 'integer',
            'archive_bytes' => 'integer',
            'ledger_bytes' => 'integer',
            'progress_done' => 'integer',
            'progress_total' => 'integer',
            'report_json' => 'array',
            'result_summary' => 'array',
            'lease_expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'confirmed_review_revision' => 'integer',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /** @return BelongsTo<OmrTemplateAuthoringDraft, $this> */
    public function resultDraft(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAuthoringDraft::class, 'result_draft_id');
    }

    /** @return BelongsTo<self, $this> */
    public function replacementFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_for_id');
    }

    /** @return HasMany<self, $this> */
    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replacement_for_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->phase, [
            self::PHASE_COMPLETED,
            self::PHASE_FAILED,
            self::PHASE_CANCELLED,
        ], true);
    }
}
