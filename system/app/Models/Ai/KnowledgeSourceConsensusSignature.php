<?php

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSourceConsensusSignature extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'knowledge_source_version_id',
        'office',
        'signer_id',
        'signed_digest',
        'reviewed_version',
        'signed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'signer_id' => 'integer',
            'reviewed_version' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KnowledgeSourceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSourceVersion::class, 'knowledge_source_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
