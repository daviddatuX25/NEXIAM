<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeRequirement extends Model
{
    use HasFactory;

    public const TRACK_ALL = 'all';

    public const TRACK_FRESHMAN = 'freshman';

    public const TRACK_TRANSFEREE = 'transferee';

    public const TRACK_ALS = 'als';

    public const TRACKS = [
        self::TRACK_ALL,
        self::TRACK_FRESHMAN,
        self::TRACK_TRANSFEREE,
        self::TRACK_ALS,
    ];

    public const KIND_DOCUMENT = 'document';

    public const KIND_CONTAINER = 'container';

    public const KINDS = [
        self::KIND_DOCUMENT,
        self::KIND_CONTAINER,
    ];

    protected $fillable = [
        'institution_id',
        'code',
        'label',
        'applicant_track',
        'item_kind',
        'is_mandatory',
        'is_active',
        'sort_order',
        'version',
    ];

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    public static function trackOptions(): array
    {
        return [
            self::TRACK_ALL => 'All Applicants',
            self::TRACK_FRESHMAN => 'Freshman (SHS Graduate)',
            self::TRACK_TRANSFEREE => 'Transferee',
            self::TRACK_ALS => 'ALS Passer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_DOCUMENT => 'Document / Paper',
            self::KIND_CONTAINER => 'Folder / Envelope',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTrack(Builder $query, string $track): Builder
    {
        return $query->whereIn('applicant_track', [self::TRACK_ALL, $track]);
    }
}
