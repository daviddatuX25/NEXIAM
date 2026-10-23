<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PublicHomepageSection extends Model
{
    protected $fillable = [
        'institution_id', 'key', 'eyebrow', 'title', 'body', 'badge_text', 'image_id',
        'image_alt', 'seed_image_src', 'image_source', 'is_active', 'version', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version' => 'integer'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(PublicHomepageMedia::class, 'image_id');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(PublicHomepageStory::class, 'section_id')->orderBy('sort_order')->orderBy('id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
