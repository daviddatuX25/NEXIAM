<?php

namespace App\Models\Notifications;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateDocument extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'communication_template_id',
        'institution_id',
        'original_name',
        'storage_path',
        'checksum',
        'mime_type',
        'file_size_bytes',
        'created_by_user_id',
    ];

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
