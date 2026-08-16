<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable child option of one pool version — never edited or deleted.
 */
class ProgramPoolOption extends Model
{
    protected $table = 'admission_attempt_program_options';

    public $timestamps = false;

    public const BASIS_PREFERENCE = 'preference';

    public const BASIS_GUIDANCE_OPTION = 'guidance_option';

    protected $fillable = [
        'program_pool_id',
        'program_id',
        'display_order',
        'basis',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProgramPool, $this>
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(ProgramPool::class, 'program_pool_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
