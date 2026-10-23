<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffVoucherBatchRowReceipt extends Model
{
    public $timestamps = false;

    protected $table = 'staff_voucher_batch_row_receipts';

    protected $fillable = [
        'actor_user_id',
        'operation_id',
        'target_user_id',
        'institution_id',
        'payload_hash',
        'public_reference',
        'outcome',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
