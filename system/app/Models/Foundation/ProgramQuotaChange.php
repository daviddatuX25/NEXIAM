<?php

namespace App\Models\Foundation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramQuotaChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'program_quota_id',
        'actor_user_id',
        'from_slots',
        'to_slots',
        'notice',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_slots' => 'integer',
            'to_slots' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProgramQuota, $this>
     */
    public function programQuota(): BelongsTo
    {
        return $this->belongsTo(ProgramQuota::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
