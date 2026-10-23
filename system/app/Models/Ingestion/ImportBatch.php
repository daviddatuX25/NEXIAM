<?php

namespace App\Models\Ingestion;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    public const STATUS_STAGED = 'staged';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_AWAITING_GUIDANCE = 'awaiting_guidance';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMMITTING = 'committing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_ALL_IN_ONE = 'all_in_one';

    public const TYPE_MULTI_SESSION = 'multi_session';

    public const TYPE_APPLICANTS_ONLY = 'applicants_only';

    public const TYPE_SCORES_ONLY = 'scores_only';

    protected $fillable = [
        'institution_id',
        'academic_cycle_id',
        'policy_version',
        'import_type',
        'file_name',
        'file_hash',
        'batch_fingerprint',
        'status',
        'topology_meta',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'skipped_rows',
        'failed_rows',
        'created_by_user_id',
        'committed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topology_meta' => 'array',
            'policy_version' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
            'committed_at' => 'datetime',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ImportBatchRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class)->orderBy('row_index');
    }
}
