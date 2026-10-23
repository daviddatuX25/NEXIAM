<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionBenchmarkExemplarValue extends Model
{
    protected static function booted(): void
    {
        $guardPublishedParent = static function (self $value): void {
            if ($value->exemplar()->whereHas('benchmarkSet', fn ($query) => $query->where('status', 'published'))->exists()) {
                throw new RuntimeException('Published benchmark exemplar values are immutable.');
            }
        };
        static::creating($guardPublishedParent);
        static::updating($guardPublishedParent);
        static::deleting($guardPublishedParent);
    }

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_missing' => 'boolean', 'numeric_value' => 'decimal:2'];
    }

    /** @return BelongsTo<AdmissionBenchmarkExemplar, $this> */
    public function exemplar(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkExemplar::class, 'exemplar_id');
    }

    /** @return BelongsTo<AdmissionBenchmarkFeature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkFeature::class, 'benchmark_feature_id');
    }
}
