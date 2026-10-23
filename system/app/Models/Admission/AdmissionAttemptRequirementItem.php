<?php

namespace App\Models\Admission;

use App\Models\Foundation\IntakeRequirement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionAttemptRequirementItem extends Model
{
    protected $fillable = [
        'requirement_set_id',
        'intake_requirement_id',
        'code',
        'label',
        'item_kind',
        'is_mandatory',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttemptRequirementSet::class, 'requirement_set_id');
    }

    public function intakeRequirement(): BelongsTo
    {
        return $this->belongsTo(IntakeRequirement::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AdmissionAttemptDocumentVerification::class, 'requirement_item_id');
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(AdmissionAttemptDocumentVerification::class, 'requirement_item_id')
            ->whereHas('requirementItem.requirementSet', fn ($query) => $query->whereColumn(
                'admission_attempt_requirement_sets.admission_attempt_id',
                'admission_attempt_document_verifications.admission_attempt_id',
            ))
            ->latestOfMany();
    }
}
