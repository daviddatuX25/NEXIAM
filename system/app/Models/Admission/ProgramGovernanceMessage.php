<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramGovernanceMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'program_governance_thread_id',
        'sender_user_id',
        'body',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<ProgramGovernanceThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ProgramGovernanceThread::class, 'program_governance_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
