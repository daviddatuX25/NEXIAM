<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeCitationFixture extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'knowledge_source_version_id',
        'kind',
        'query_text',
        'expected_excerpt',
        'expected_outcome',
    ];

    /**
     * @return BelongsTo<KnowledgeSourceVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSourceVersion::class, 'knowledge_source_version_id');
    }
}
