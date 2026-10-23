<?php

declare(strict_types=1);

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    public const CATEGORY_ADMISSIONS = 'admissions';

    public const CATEGORY_TESTING = 'testing';

    public const CATEGORY_CAMPUS = 'campus';

    public const OFFICE_ADMIN = 'admin';

    public const OFFICE_REGISTRAR = 'registrar';

    public const OFFICE_GUIDANCE = 'guidance';

    public const SOURCE_CLASS_POLICY = 'institution_policy';

    public const SOURCE_CLASS_PUBLIC_FAQ = 'public_faq';

    public const AUDIENCE_STAFF_ONLY = 'staff_only';

    public const AUDIENCE_STAFF_AND_APPLICANT_INQUIRY = 'staff_and_applicant_inquiry';

    public const AUDIENCE_CROSS_OFFICE_STAFF = 'cross_office_staff';

    public const CLASSIFICATION_OFFICE_INTERNAL = 'office_internal';

    public const CLASSIFICATION_PUBLIC_INSTITUTIONAL = 'public_institutional';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'key',
        'title',
        'source_class',
        'audience',
        'purpose',
        'category',
        'classification',
        'institution_id',
        'owning_office',
        'owner_user_id',
    ];

    /**
     * @return HasMany<KnowledgeSourceVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeSourceVersion::class, 'knowledge_source_id')->orderByDesc('version');
    }

    /**
     * @return HasMany<KnowledgeSourceReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(KnowledgeSourceReconciliation::class, 'knowledge_source_id');
    }
}
