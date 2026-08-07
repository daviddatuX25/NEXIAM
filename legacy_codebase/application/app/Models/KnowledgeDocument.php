<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KnowledgeDocument extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CSV_IMPORT = 'csv_import';

    public const SYNC_PENDING = 'pending';

    public const SYNC_INDEXED = 'indexed';

    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'title',
        'content',
        'metadata',
        'source',
        'is_active',
        'mxb_file_id',
        'mxb_sync_status',
        'mxb_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'mxb_synced_at' => 'datetime',
        ];
    }

    /**
     * Scope: only active docs (for retrieval).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Compact metadata for display (e.g. "Engineering · 2024").
     */
    public function getMetadataSummaryAttribute(): string
    {
        if (! is_array($this->metadata) || empty($this->metadata)) {
            return '—';
        }

        $parts = [];
        if (! empty($this->metadata['category'])) {
            $parts[] = $this->metadata['category'];
        }
        if (! empty($this->metadata['year'])) {
            $parts[] = $this->metadata['year'];
        }
        if (! empty($this->metadata['description'])) {
            $parts[] = Str::limit($this->metadata['description'], 30);
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }
}
