<?php

namespace App\Models\Admission;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionBenchmarkFeature extends Model
{
    protected static function booted(): void
    {
        $guardPublishedParent = static function (self $feature): void {
            if ($feature->benchmarkSet()->value('status') === 'published') {
                throw new RuntimeException('Published benchmark features are immutable.');
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
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
            'source_definition_json' => 'array',
        ];
    }

    /** @return BelongsTo<AdmissionBenchmarkSet, $this> */
    public function benchmarkSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkSet::class, 'benchmark_set_id');
    }

    /** @return BelongsTo<AptitudeArea, $this> */
    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }
}
