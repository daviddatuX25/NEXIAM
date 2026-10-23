<?php

namespace App\Models;

use App\Authorization\ActorScope;
use App\Models\Admission\ApplicantAccount;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * @return HasOne<StaffAuthenticationState, $this>
     */
    public function authenticationState(): HasOne
    {
        return $this->hasOne(StaffAuthenticationState::class, 'user_id');
    }

    /**
     * @return HasMany<StaffActivationVoucher, $this>
     */
    public function activationVouchers(): HasMany
    {
        return $this->hasMany(StaffActivationVoucher::class, 'user_id');
    }

    /**
     * @return HasMany<StaffProviderIdentity, $this>
     */
    public function providerIdentities(): HasMany
    {
        return $this->hasMany(StaffProviderIdentity::class, 'user_id');
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
        $profile = $this->staffProfile()->first();

        if ($profile === null || ! $profile->isActive() || $profile->institution_id === null) {
            return ActorScope::empty();
        }

        $programCodes = [];
        $departmentId = null;

        if ($profile->isAcademic()) {
            $assignedCodes = $profile->program_codes ?? [];

            if (is_array($assignedCodes) && $assignedCodes !== []) {
                $programCodes = Program::query()
                    ->where('institution_id', $profile->institution_id)
                    ->where('is_active', true)
                    ->whereIn('code', $assignedCodes)
                    ->pluck('code')
                    ->all();
            }

            $departmentIds = Department::query()
                ->where('dean_user_id', $this->getKey())
                ->where('institution_id', $profile->institution_id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            if ($departmentIds !== []) {
                $departmentCodes = Program::query()
                    ->whereIn('department_id', $departmentIds)
                    ->where('institution_id', $profile->institution_id)
                    ->where('is_active', true)
                    ->pluck('code')
                    ->all();
                $programCodes = array_values(array_unique(array_merge($programCodes, $departmentCodes)));
            }

            $departmentId = count($departmentIds) === 1 ? $departmentIds[0] : null;
        }

        return new ActorScope(
            institutionId: $profile->institution_id,
            office: $profile->office,
            programCodes: $programCodes,
            activeCycleCode: $profile->active_cycle_code,
            departmentId: $departmentId,
        );
    }
}
