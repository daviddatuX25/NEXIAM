<?php

namespace App\Models\Examination;

use App\Models\Assessment\OmrBookletSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ExamSessionMaterialSet extends Model
{
    protected $table = 'exam_session_material_sets';

    protected $fillable = [
        'snapshot_id',
        'booklet_set_id',
        'set_code',
        'order_hash',
        'answer_key_hash',
        'answer_key_json',
    ];

    protected function casts(): array
    {
        return ['answer_key_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new RuntimeException('Session material Set snapshots are immutable.');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('Session material Set snapshots are immutable.');
        });
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ExamSessionMaterialSnapshot::class, 'snapshot_id');
    }

    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }
}
