<?php

namespace App\Models\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'document_mode' => 'none',
        'content_schema_version' => 'notice-content-1',
        'email_renderer_version' => 'legacy',
        'paper_size' => null,
        'orientation' => null,
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Published notice versions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Published notice versions are retained.');
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'communication_template_id',
        'version',
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
        'email_blueprint',
        'email_renderer_version',
        'import_provenance',
        'content_sha256',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'document_revision_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'builder_json' => 'array',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
