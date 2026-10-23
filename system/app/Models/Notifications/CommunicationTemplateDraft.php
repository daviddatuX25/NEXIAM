<?php

namespace App\Models\Notifications;

use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateDraft extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
        'stage' => 1,
        'document_mode' => 'none',
        'content_schema_version' => 'notice-content-1',
        'email_renderer_version' => 'legacy',
        'paper_size' => null,
        'orientation' => null,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'communication_template_id',
        'base_version',
        'lock_version',
        'stage',
        'content_schema_version',
        'subject',
        'body',
        'email_subject',
        'email_body',
        'in_app_title',
        'in_app_body',
        'action_label',
        'action_route',
        'document_mode',
        'document_asset_id',
        'document_revision_id',
        'builder_json',
        'paper_size',
        'orientation',
        'document_source_drafts',
        'email_blueprint',
        'email_renderer_version',
        'import_provenance',
        'content_sha256',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_version' => 'integer',
            'lock_version' => 'integer',
            'document_revision_id' => 'integer',
            'stage' => 'integer',
            'builder_json' => 'array',
            'document_source_drafts' => 'array',
            'email_blueprint' => 'array',
            'import_provenance' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommunicationTemplateDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateDocument::class, 'document_asset_id');
    }

    /**
     * @return BelongsTo<NoticeDocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(NoticeDocumentVersion::class, 'document_revision_id');
    }

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function computeContentDigest(): string
    {
        return app(CommunicationTemplateState::class)->digest($this->template, $this);
    }
}
