<?php

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSourcePolicyDependency extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'knowledge_source_version_id',
        'public_policy_id',
        'public_policy_revision_id',
        'dependency_type',
        'policy_digest_at_link',
        'linked_by',
        'linked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'public_policy_id' => 'integer',
            'public_policy_revision_id' => 'integer',
            'linked_by' => 'integer',
            'linked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KnowledgeSourceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSourceVersion::class, 'knowledge_source_version_id');
    }

    /** @return BelongsTo<PublicPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PublicPolicy::class, 'public_policy_id');
    }

    /** @return BelongsTo<PublicPolicyRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(PublicPolicyRevision::class, 'public_policy_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
