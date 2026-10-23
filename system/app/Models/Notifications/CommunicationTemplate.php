<?php

namespace App\Models\Notifications;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommunicationTemplate extends Model
{
    use HasUuids;

    protected $attributes = [
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'domain',
        'event_key',
        'slug',
        'name',
        'description',
        'active_version',
        'family_revision',
        'document_revision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_version' => 'integer',
            'family_revision' => 'integer',
            'document_revision' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<CommunicationTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CommunicationTemplateVersion::class);
    }

    public function activeVersion(): ?CommunicationTemplateVersion
    {
        return $this->versions()->where('version', $this->active_version)->first();
    }

    /**
     * @return HasOne<CommunicationTemplateDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(CommunicationTemplateDraft::class);
    }

    public function documentDraft(): HasOne
    {
        return $this->hasOne(NoticeDocumentDraft::class);
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(NoticeDocumentVersion::class);
    }

    public function latestDocumentVersion(): ?NoticeDocumentVersion
    {
        return $this->documentVersions()->orderByDesc('version')->first();
    }

    /**
     * @return HasMany<CommunicationTemplateReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(CommunicationTemplateReceipt::class, 'canonical_slug', 'slug')
            ->where('institution_id', $this->institution_id)
            ->where('domain', $this->domain);
    }
}
