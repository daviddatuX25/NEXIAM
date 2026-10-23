<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAdvisingNoteRevision extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Advising note revisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Advising note revisions are immutable.');
        });
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    /** @return BelongsTo<ProgramAdvisingNote, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNote::class, 'program_advising_note_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by_user_id');
    }

    /** @return BelongsTo<ProgramAdvisingNoteRevision, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_revision_id');
    }
}
