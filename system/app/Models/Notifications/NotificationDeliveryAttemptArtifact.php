<?php

namespace App\Models\Notifications;

use App\Notifications\NotificationDelivery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryAttemptArtifact extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'delivery_id',
        'attempt',
        'checksum',
        'mime_type',
        'file_size_bytes',
        'source_sha256',
        'source_kind',
        'canonical_binding',
    ];

    protected function casts(): array
    {
        return ['canonical_binding' => 'array'];
    }

    /**
     * @return BelongsTo<NotificationDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'delivery_id');
    }
}
