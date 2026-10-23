<?php

namespace App\Testing;

use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeFixtureSet extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected $attributes = ['status' => 'active', 'revision' => 1];

    protected function casts(): array
    {
        return [
            'actor_bindings' => 'array',
            'material_snapshot' => 'array',
            'credentials' => 'encrypted:array',
            'revision' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allowsActor(User $actor): bool
    {
        return (int) $this->created_by_user_id === (int) $actor->getKey()
            || in_array((int) $actor->getKey(), array_values($this->actor_bindings ?? []), true);
    }
}
