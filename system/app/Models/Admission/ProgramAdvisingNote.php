<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramAdvisingNote extends Model
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['current_version' => 0, 'audience' => 'program'];

    protected function casts(): array
    {
        return ['current_version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<ProgramAdvisingNoteRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNoteRevision::class, 'current_revision_id');
    }

    /** @return BelongsTo<ProgramAdvisingNoteRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNoteRevision::class, 'published_revision_id');
    }

    /** @return BelongsTo<ProgramAdvisingNotePublication, $this> */
    public function publishedPublication(): BelongsTo
    {
        return $this->belongsTo(ProgramAdvisingNotePublication::class, 'published_publication_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<AcademicCycle, $this> */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /** @return HasMany<ProgramAdvisingNoteRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProgramAdvisingNoteRevision::class);
    }

    /** @return HasMany<ProgramAdvisingNotePublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(ProgramAdvisingNotePublication::class);
    }

    /** @return HasMany<ProgramAdvisingNoteUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(ProgramAdvisingNoteUsage::class);
    }
}
