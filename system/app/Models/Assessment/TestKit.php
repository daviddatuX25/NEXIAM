<?php

namespace App\Models\Assessment;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class TestKit extends Model
{
    protected $fillable = [
        'public_id',
        'institution_id',
        'code',
        'name',
        'is_synthetic',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'is_synthetic' => 'boolean',
            'created_by_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $kit): void {
            foreach ([
                'public_id',
                'institution_id',
                'code',
                'is_synthetic',
                'created_by_user_id',
                'created_at',
            ] as $field) {
                if ($kit->isDirty($field)) {
                    throw new RuntimeException('test_kit_family_owner_and_provenance_immutable');
                }
            }
        });
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<TestKitVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TestKitVersion::class, 'test_kit_id')->orderBy('version_number');
    }

    /**
     * @return HasMany<TestKitPreparation, $this>
     */
    public function preparations(): HasMany
    {
        return $this->hasMany(TestKitPreparation::class, 'test_kit_id')->orderBy('created_at');
    }

    /**
     * @return HasOne<TestKitPreparation, $this>
     */
    public function openPreparation(): HasOne
    {
        return $this->hasOne(TestKitPreparation::class, 'test_kit_id')
            ->where('state', TestKitPreparation::STATE_PREPARING);
    }
}
