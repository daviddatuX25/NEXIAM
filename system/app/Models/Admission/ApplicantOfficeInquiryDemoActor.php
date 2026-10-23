<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantOfficeInquiryDemoActor extends Model
{
    public const NAMESPACE = 'applicant-office-inquiry-demo';

    public const TYPE_APPLICANT = 'applicant';

    public const TYPE_STAFF = 'staff';

    protected $table = 'applicant_office_inquiry_demo_actors';

    protected $attributes = [
        'namespace' => self::NAMESPACE,
    ];

    protected $fillable = [
        'user_id',
        'institution_id',
        'actor_type',
        'namespace',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
