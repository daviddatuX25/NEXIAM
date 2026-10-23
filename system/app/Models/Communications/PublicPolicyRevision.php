<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicPolicyRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [];

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function contentDigest(): string
    {
        return hash('sha256', $this->title."\0".$this->content);
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Public policy revisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Public policy revisions are retained permanently.');
        });
    }

    /** @return BelongsTo<PublicPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PublicPolicy::class, 'public_policy_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
