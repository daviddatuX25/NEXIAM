<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRevision extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Announcement revisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Announcement revisions are retained.');
        });
    }

    protected $fillable = [
        'announcement_id', 'program_id', 'revision', 'publisher_key', 'publisher_label', 'title', 'summary', 'body',
        'priority', 'is_featured', 'status', 'starts_at', 'ends_at', 'published_at', 'actor_id', 'actor_name', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'program_id' => 'integer',
            'revision' => 'integer',
            'is_featured' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
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
