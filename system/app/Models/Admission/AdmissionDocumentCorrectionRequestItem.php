<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDocumentCorrectionRequestItem extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Admission document correction request items are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Admission document correction request items are immutable.');
        });
    }

    protected $fillable = [
        'document_correction_request_id',
        'requirement_item_id',
        'expected_latest_verification_id',
        'label',
        'observed_status',
        'applicant_instruction',
    ];

    protected function casts(): array
    {
        return [
            'expected_latest_verification_id' => 'integer',
            'requirement_item_id' => 'integer',
        ];
    }

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(AdmissionDocumentCorrectionRequest::class, 'document_correction_request_id');
    }

    public function requirementItem(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttemptRequirementItem::class, 'requirement_item_id');
    }
}
