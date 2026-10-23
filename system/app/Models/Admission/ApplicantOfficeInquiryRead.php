<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantOfficeInquiryRead extends Model
{
    protected $table = 'applicant_office_inquiry_reads';

    protected $attributes = [
        'last_read_sequence' => 0,
    ];

    protected $fillable = [
        'applicant_office_inquiry_id',
        'user_id',
        'last_read_sequence',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_sequence' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApplicantOfficeInquiry, $this>
     */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(ApplicantOfficeInquiry::class, 'applicant_office_inquiry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
