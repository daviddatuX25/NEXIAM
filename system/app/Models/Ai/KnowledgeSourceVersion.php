<?php

declare(strict_types=1);

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KnowledgeSourceVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_REVIEW_REQUIRED = 'review_required';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_INDEXING = 'indexing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_INDEX_FAILED = 'index_failed';

    public const STATUS_DELETION_PENDING = 'deletion_pending';

    public const STATUS_DELETED = 'deleted';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'knowledge_source_id',
        'version',
        'status',
        'title',
        'body_text',
        'question',
        'answer',
        'content_checksum',
        'quarantine_path',
        'extraction_digest',
        'extraction_text',
        'publication_digest',
        'created_by',
        'approved_by',
        'approved_at',
        'published_at',
        'revoked_at',
        'revoke_reason',
        'failure_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KnowledgeSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /** @return HasMany<KnowledgeCitationFixture, $this> */
    public function fixtures(): HasMany
    {
        return $this->hasMany(KnowledgeCitationFixture::class, 'knowledge_source_version_id');
    }

    /** @return HasOne<KnowledgeIndexEntry, $this> */
    public function indexEntry(): HasOne
    {
        return $this->hasOne(KnowledgeIndexEntry::class, 'knowledge_source_version_id');
    }

    /** @return HasMany<KnowledgeSourceApproval, $this> */
    public function officeApprovals(): HasMany
    {
        return $this->hasMany(KnowledgeSourceApproval::class, 'knowledge_source_version_id')->orderBy('office');
    }

    /** @return HasMany<KnowledgeSourceConsensusSignature, $this> */
    public function consensusSignatures(): HasMany
    {
        return $this->hasMany(KnowledgeSourceConsensusSignature::class, 'knowledge_source_version_id');
    }

    /** @return HasMany<KnowledgeSourcePolicyDependency, $this> */
    public function policyDependencies(): HasMany
    {
        return $this->hasMany(KnowledgeSourcePolicyDependency::class, 'knowledge_source_version_id');
    }

    /** @return HasMany<KnowledgeSourceReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(KnowledgeSourceReconciliation::class, 'knowledge_source_version_id');
    }

    /** @return HasOne<KnowledgeSourceReconciliation, $this> */
    public function latestReconciliation(): HasOne
    {
        return $this->hasOne(KnowledgeSourceReconciliation::class, 'knowledge_source_version_id')->latestOfMany();
    }
}
