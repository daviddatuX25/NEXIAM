<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NoticeDocumentDraft extends Model
{
    protected $attributes = ['base_version' => 0, 'document_revision' => 1, 'lock_version' => 1, 'stage' => 1];

    protected $fillable = ['communication_template_id', 'base_version', 'document_revision', 'lock_version', 'stage', 'state'];

    protected function casts(): array
    {
        return ['base_version' => 'integer', 'document_revision' => 'integer', 'lock_version' => 'integer', 'stage' => 'integer', 'state' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }
}
