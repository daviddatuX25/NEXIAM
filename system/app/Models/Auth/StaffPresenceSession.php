<?php

declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPresenceSession extends Model
{
    protected $table = 'staff_presence_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'user_id', 'institution_id', 'office', 'session_id_hash', 'auth_session_version',
        'device_type', 'browser', 'os', 'ip_address', 'current_path', 'workspace_area',
        'logged_in_at', 'first_observed_at', 'last_active_at', 'ended_at', 'end_reason',
    ];

    /** @var list<string> */
    protected $hidden = ['session_id_hash', 'ip_address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'auth_session_version' => 'integer',
            'logged_in_at' => 'datetime',
            'first_observed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'user_id', 'user_id');
    }
}
