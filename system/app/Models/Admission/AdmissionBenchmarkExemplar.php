<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class AdmissionBenchmarkExemplar extends Model
{
    protected static function booted(): void
    {
        $guardPublishedParent = static function (self $exemplar): void {
            if ($exemplar->benchmarkSet()->value('status') === 'published') {
                throw new RuntimeException('Published benchmark exemplars are immutable.');
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
        return ['tier_level' => 'integer', 'target_class' => 'integer'];
    }

    /** @return BelongsTo<AdmissionBenchmarkSet, $this> */
    public function benchmarkSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkSet::class, 'benchmark_set_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<AdmissionBenchmarkExemplarValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkExemplarValue::class, 'exemplar_id');
    }
}
