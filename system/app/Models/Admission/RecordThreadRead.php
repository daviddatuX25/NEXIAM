<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-participant read watermark for a record thread.
 */
class RecordThreadRead extends Model
{
    protected $table = 'record_thread_reads';

    public $timestamps = false;

    protected $fillable = [
        'record_thread_id',
        'user_id',
        'last_read_sequence',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_sequence' => 'integer',
            'last_read_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
