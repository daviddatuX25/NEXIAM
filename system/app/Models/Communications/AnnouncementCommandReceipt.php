<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementCommandReceipt extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Announcement command receipts are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Announcement command receipts are retained.');
        });
    }

    protected $fillable = [
        'actor_id', 'request_id', 'operation', 'required_capability', 'announcement_id', 'expected_revision', 'payload_hash', 'result', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_revision' => 'integer',
            'result' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
