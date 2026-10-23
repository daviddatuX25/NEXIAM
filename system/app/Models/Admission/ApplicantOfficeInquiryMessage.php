<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantOfficeInquiryMessage extends Model
{
    public const ROLE_APPLICANT = 'applicant';

    public const ROLE_STAFF = 'staff';

    protected $table = 'applicant_office_inquiry_messages';

    public $timestamps = false;

    protected $attributes = [
        'sender_role' => self::ROLE_APPLICANT,
    ];

    protected $fillable = [
        'applicant_office_inquiry_id',
        'sender_user_id',
        'sender_role',
        'sequence',
        'body',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'body' => 'encrypted',
            'created_at' => 'datetime',
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
