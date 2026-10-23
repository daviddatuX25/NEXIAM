<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PublicHomepageStory extends Model
{
    protected $fillable = [
        'institution_id', 'section_id', 'seed_key', 'eyebrow', 'title', 'body', 'image_id',
        'image_alt', 'seed_image_src', 'image_source', 'cta_label', 'cta_href', 'align',
        'sort_order', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PublicHomepageSection::class, 'section_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(PublicHomepageMedia::class, 'image_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
