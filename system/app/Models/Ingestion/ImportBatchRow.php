<?php

namespace App\Models\Ingestion;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchRow extends Model
{
    public const STATUS_STAGED = 'staged';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_APPLICANT_IMPORTED = 'applicant_imported';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'import_batch_id',
        'sheet_name',
        'row_index',
        'idempotency_key',
        'status',
        'raw_payload',
        'normalized_payload',
        'errors',
        'reference_code',
        'target_exam_session_id',
        'admission_attempt_id',
        'imported_at',
        'guidance_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_index' => 'integer',
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'errors' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function targetExamSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'target_exam_session_id');
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }
}
