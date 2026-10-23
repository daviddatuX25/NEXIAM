<?php

declare(strict_types=1);

namespace App\Models\ResultSheet;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultDocumentPrintReceipt extends Model
{
    protected $fillable = [
        'institution_id',
        'admission_attempt_id',
        'result_document_id',
        'confirmed_by_user_id',
        'confirmed_at',
        'job_item_id',
        'operation',
        'correction_reason',
        'correction_predecessor_id',
        'evidence_revision',
        'is_current',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'immutable_datetime',
            'evidence_revision' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    /** @return BelongsTo<AdmissionAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /** @return BelongsTo<ResultDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ResultDocument::class, 'result_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /** @return BelongsTo<PrintJobItem, $this> */
    public function jobItem(): BelongsTo
    {
        return $this->belongsTo(PrintJobItem::class, 'job_item_id');
    }
}
