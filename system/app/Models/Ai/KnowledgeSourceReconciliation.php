<?php

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSourceReconciliation extends Model
{
    use HasUuids;

    public const DISPOSITION_RETAIN_INTERNAL = 'retain_internal';

    public const DISPOSITION_REFERENCE_PUBLIC = 'reference_public';

    public const DISPOSITION_HOLD = 'hold';

    protected $fillable = [
        'knowledge_source_id',
        'knowledge_source_version_id',
        'disposition',
        'target_type',
        'target_id',
        'target_version',
        'checksum_at_review',
        'reviewed_by',
        'reviewed_at',
        'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'target_version' => 'integer',
        ];
    }

    /** @return BelongsTo<KnowledgeSource, $this> */
    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /** @return BelongsTo<KnowledgeSourceVersion, $this> */
    public function knowledgeSourceVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSourceVersion::class, 'knowledge_source_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
