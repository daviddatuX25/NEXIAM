<?php

namespace App\Models\Printing;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultRelease;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJobItem extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_FILLING = 'filling';

    public const STATUS_CONVERTING = 'converting';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $attributes = ['work_unit_type' => 'student'];

    protected $fillable = [
        'print_job_id',
        'admission_attempt_id',
        'item_key',
        'work_unit_type',
        'result_release_id',
        'item_index',
        'status',
        'file_path',
        'file_checksum',
        'docx_path',
        'docx_checksum',
        'safe_error',
        'claim_token',
        'claimed_at',
        'claim_expires_at',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'item_index' => 'integer',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PrintJob, $this>
     */
    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<ResultRelease, $this>
     */
    public function resultRelease(): BelongsTo
    {
        return $this->belongsTo(ResultRelease::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
