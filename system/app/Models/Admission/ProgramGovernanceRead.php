<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramGovernanceRead extends Model
{
    protected $fillable = [
        'program_governance_thread_id',
        'user_id',
        'last_read_message_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    /** @return BelongsTo<ProgramGovernanceThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ProgramGovernanceThread::class, 'program_governance_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProgramGovernanceMessage, $this> */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ProgramGovernanceMessage::class, 'last_read_message_id');
    }
}
