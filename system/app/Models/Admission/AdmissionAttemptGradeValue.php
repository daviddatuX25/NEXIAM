<?php

namespace App\Models\Admission;

use App\Models\Foundation\IntakeGradeField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAttemptGradeValue extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'intake_grade_field_id',
        'field_code',
        'field_label',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<IntakeGradeField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(IntakeGradeField::class, 'intake_grade_field_id');
    }
}
