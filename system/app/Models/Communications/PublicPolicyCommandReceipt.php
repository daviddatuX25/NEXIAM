<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicPolicyCommandReceipt extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['result_snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Public policy command receipts are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Public policy command receipts are retained permanently.');
        });
    }

    /** @return BelongsTo<PublicPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PublicPolicy::class, 'public_policy_id');
    }
}
