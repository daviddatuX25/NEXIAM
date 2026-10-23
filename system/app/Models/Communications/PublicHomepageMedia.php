<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PublicHomepageMedia extends Model
{
    protected $fillable = [
        'institution_id', 'storage_disk', 'storage_path', 'sha256', 'mime_type', 'byte_size',
        'width', 'height', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PublicHomepageSection::class, 'image_id');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(PublicHomepageStory::class, 'image_id');
    }
}
