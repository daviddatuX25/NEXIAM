<?php

namespace App\Models\Walkthrough;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkthroughProgress extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'workspace_key',
        'surface',
        'page_key',
        'revision',
        'cursor',
        'disposition',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'disposition' => 'in_progress',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
