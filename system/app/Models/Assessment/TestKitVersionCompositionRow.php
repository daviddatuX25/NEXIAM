<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestKitVersionCompositionRow extends Model
{
    protected $table = 'test_kit_version_composition_rows';

    protected $fillable = [
        'test_kit_version_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
        'test_instrument_version_id',
        'composition_revision',
        'composition_sha256',
        'disposition',
        'physical_item_uid',
        'sheet_item_id',
        'booklet_set_item_id',
        'booklet_item_id',
        'booklet_item_uid',
        'booklet_section_uid',
        'test_instrument_subtest_id',
        'test_instrument_item_id',
    ];

    protected function casts(): array
    {
        return [
            'test_kit_version_id' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'booklet_set_id' => 'integer',
            'test_instrument_version_id' => 'integer',
            'composition_revision' => 'integer',
            'sheet_item_id' => 'integer',
            'booklet_set_item_id' => 'integer',
            'booklet_item_id' => 'integer',
            'test_instrument_subtest_id' => 'integer',
            'test_instrument_item_id' => 'integer',
        ];
    }

    /** @return BelongsTo<TestKitVersion, $this> */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /** @return BelongsTo<OmrTemplateQuestion, $this> */
    public function sheetItem(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateQuestion::class, 'sheet_item_id');
    }

    /** @return BelongsTo<OmrBookletSetItem, $this> */
    public function bookletSetItem(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSetItem::class, 'booklet_set_item_id');
    }

    /** @return BelongsTo<OmrBookletItem, $this> */
    public function bookletItem(): BelongsTo
    {
        return $this->belongsTo(OmrBookletItem::class, 'booklet_item_id');
    }

    /** @return BelongsTo<TestInstrumentItem, $this> */
    public function formalItem(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentItem::class, 'test_instrument_item_id');
    }
}
