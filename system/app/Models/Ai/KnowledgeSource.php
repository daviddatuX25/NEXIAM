<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'key',
        'title',
        'source_class',
        'audience',
        'purpose',
        'classification',
        'institution_id',
        'owner_user_id',
    ];

    /**
     * @return HasMany<KnowledgeSourceVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeSourceVersion::class, 'knowledge_source_id')->orderByDesc('version');
    }
}
