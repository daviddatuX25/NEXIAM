<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id', 'publisher_key', 'publisher_label', 'program_id', 'title', 'summary', 'body',
        'priority', 'is_featured', 'status', 'revision', 'starts_at', 'ends_at', 'published_at',
        'created_by', 'published_by', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'is_featured' => false,
        'status' => 'draft',
        'revision' => 1,
    ];

    protected static function newFactory(): Factory
    {
        return AnnouncementFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'program_id' => 'integer',
            'is_featured' => 'boolean',
            'revision' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<AnnouncementRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(AnnouncementRevision::class)->orderBy('revision');
    }

    /** @param Builder<static> $query @return Builder<static> */
    public function scopePubliclyEligible(Builder $query, int $institutionId, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query->where('institution_id', $institutionId)
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('program_id')->whereIn('publisher_key', ['office:admin', 'office:registrar', 'office:guidance']);
                })->orWhere(function (Builder $query): void {
                    $query->whereIn('publisher_key', ['office:admin', 'office:registrar', 'office:guidance'])
                        ->whereHas('program', fn (Builder $program): Builder => $program->active()->whereColumn('programs.institution_id', 'announcements.institution_id'));
                })->orWhere(function (Builder $query): void {
                    $query->whereNotNull('program_id')
                        ->whereHas('program', fn (Builder $program): Builder => $program->active()
                            ->whereColumn('programs.institution_id', 'announcements.institution_id')
                            ->whereRaw("announcements.publisher_key = 'program:' || programs.code"));
                });
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            });
    }

    public function effectiveStatus(?Carbon $now = null): string
    {
        $now ??= now();

        if ($this->status !== 'published') {
            return $this->status;
        }

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && ! $this->ends_at->isAfter($now)) {
            return 'expired';
        }

        return 'published';
    }
}
