<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrQrDisabledIssuance extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VOID = 'void';

    protected $table = 'omr_qr_disabled_issuances';

    protected $attributes = ['status' => self::STATUS_QUEUED];

    protected $fillable = [
        'template_version_id', 'institution_id', 'academic_cycle_id', 'print_job_id',
        'issuance_code', 'page_count', 'copy_policy', 'intended_copy_count', 'status',
        'artifact_manifest_hash', 'created_by_user_id', 'printed_at', 'failed_at', 'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'template_version_id' => 'integer',
            'institution_id' => 'integer',
            'academic_cycle_id' => 'integer',
            'print_job_id' => 'integer',
            'page_count' => 'integer',
            'intended_copy_count' => 'integer',
            'created_by_user_id' => 'integer',
            'printed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function pageClaims(): HasMany
    {
        return $this->hasMany(OmrQrDisabledPageClaim::class, 'issuance_id');
    }
}
