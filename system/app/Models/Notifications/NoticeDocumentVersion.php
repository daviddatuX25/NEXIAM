<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NoticeDocumentVersion extends Model
{
    protected $fillable = ['communication_template_id', 'version', 'state', 'render_sha256', 'definition_sha256', 'proof_receipt', 'created_by_user_id'];

    protected $hidden = ['proof_receipt'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Ready notice document versions are immutable.');
        });
        self::deleting(static function (): never {
            throw new \LogicException('Ready notice document versions are retained.');
        });
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'state' => 'array', 'proof_receipt' => 'encrypted'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }
}
