<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only message inside a server-owned record thread.
 */
class RecordThreadMessage extends Model
{
    protected $table = 'record_thread_messages';

    public $timestamps = false;

    protected $fillable = [
        'record_thread_id',
        'sender_user_id',
        'sequence',
        'body',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecordThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(RecordThread::class, 'record_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
