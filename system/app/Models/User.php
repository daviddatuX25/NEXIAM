<?php

namespace App\Models;

use App\Authorization\ActorScope;
use App\Models\Admission\ApplicantAccount;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * @return HasOne<ApplicantAccount, $this>
     */
    public function applicantAccount(): HasOne
    {
        return $this->hasOne(ApplicantAccount::class);
    }

    public function actorScope(): ActorScope
    {
        $profile = $this->staffProfile;

        if ($profile === null) {
            return ActorScope::empty();
        }

        return new ActorScope(
            institutionId: $profile->institution_id,
            office: $profile->office,
            programCodes: $profile->program_codes ?? [],
            activeCycleCode: $profile->active_cycle_code,
        );
    }
}
