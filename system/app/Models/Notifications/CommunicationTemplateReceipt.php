<?php

namespace App\Models\Notifications;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateReceipt extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'institution_id',
        'domain',
        'canonical_slug',
        'operation',
        'idempotency_key',
        'payload_sha256',
        'response_payload',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
