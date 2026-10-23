<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrBookletSection extends Model
{
    protected $table = 'omr_booklet_sections';

    protected $fillable = [
        'booklet_version_id',
        'booklet_section_uid',
        'name',
        'display_order',
        'expected_count',
        'option_tokens',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'expected_count' => 'integer',
            'option_tokens' => 'array',
        ];
    }

    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OmrBookletItem::class, 'booklet_section_id');
    }
}
