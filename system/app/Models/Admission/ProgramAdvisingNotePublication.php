<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAdvisingNotePublication extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Advising note publication receipts are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Advising note publication receipts are immutable.');
        });
    }

    protected function casts(): array
    {
        return ['expected_version' => 'integer', 'recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<ProgramAdvisingNote, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNote::class, 'program_advising_note_id');
    }

    /** @return BelongsTo<ProgramAdvisingNoteRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNoteRevision::class, 'revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
