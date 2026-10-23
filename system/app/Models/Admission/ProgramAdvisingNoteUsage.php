<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAdvisingNoteUsage extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Advising note usage receipts are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Advising note usage receipts are immutable.');
        });
    }

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    /** @return BelongsTo<AdmissionAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
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

    /** @return BelongsTo<ProgramAdvisingNotePublication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNotePublication::class, 'publication_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
