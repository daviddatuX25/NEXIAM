<?php

declare(strict_types=1);

namespace App\Models\Staffing;

use Illuminate\Database\Eloquent\Model;

final class StaffAccountOperationReceipt extends Model
{
    protected $fillable = ['operation_id', 'actor_id', 'institution_id', 'purpose', 'payload_digest', 'outcome', 'target_user_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
