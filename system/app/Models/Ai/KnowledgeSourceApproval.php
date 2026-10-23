<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSourceApproval extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'knowledge_source_version_id',
        'office',
        'approver_id',
        'approved_at',
        'publication_digest',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    /** @return BelongsTo<KnowledgeSourceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSourceVersion::class, 'knowledge_source_version_id');
    }
}
