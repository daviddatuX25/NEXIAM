<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntakeGradeField extends Model
{
    public const KIND_GWA = 'gwa';

    public const KIND_SUBJECT = 'subject';

    protected $fillable = [
        'institution_id',
        'code',
        'label',
        'field_kind',
        'is_required',
        'is_active',
        'sort_order',
        'min_value',
        'max_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
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
     * @return HasMany<\App\Models\Admission\AdmissionAttemptGradeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(\App\Models\Admission\AdmissionAttemptGradeValue::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function activeForInstitution(int $institutionId)
    {
        return static::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
