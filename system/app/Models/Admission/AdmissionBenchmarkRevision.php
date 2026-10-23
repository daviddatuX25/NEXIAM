<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionBenchmarkRevision extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Benchmark revisions are append-only.');
        });
        static::deleting(function (): never {
            throw new RuntimeException('Benchmark revisions are append-only.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'old_content' => 'array', 'content' => 'array', 'feature_schema_json' => 'array'];
    }

    /** @return BelongsTo<AdmissionBenchmarkDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkDraft::class, 'draft_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<self, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_revision_id');
    }
}
