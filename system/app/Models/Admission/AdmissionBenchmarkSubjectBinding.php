<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionBenchmarkSubjectBinding extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException('Approved benchmark subject bindings are immutable; create a new version.');
        });
        static::deleting(static function (): never {
            throw new RuntimeException('Approved benchmark subject bindings are immutable.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'is_model_required' => 'boolean',
            'source_snapshot_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<IntakeGradeField, $this> */
    public function sourceField(): BelongsTo
    {
        return $this->belongsTo(IntakeGradeField::class, 'intake_grade_field_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
