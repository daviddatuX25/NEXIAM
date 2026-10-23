<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrTemplateItemMapping extends Model
{
    protected static function booted(): void
    {
        static::saving(static function (self $mapping): void {
            $mapping->assertCompiledParentMutable();
        });

        static::deleting(static function (self $mapping): void {
            $mapping->assertCompiledParentMutable();
        });
    }

    private function assertCompiledParentMutable(): void
    {
        $versionIds = array_unique(array_filter([
            $this->exists ? $this->getRawOriginal('template_version_id') : null,
            $this->template_version_id,
        ]));

        if (OmrAnswerSheetTemplateVersion::query()
            ->whereIn('id', $versionIds)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->exists()) {
            throw new \RuntimeException('Compiled answer-sheet mappings are immutable.');
        }
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_version_id',
        'test_instrument_version_id',
        'test_instrument_subtest_id',
        'test_instrument_item_id',
        'native_label',
        'page_no',
        'global_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
            'global_position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OmrAnswerSheetTemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestInstrumentSubtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentSubtest::class, 'test_instrument_subtest_id');
    }

    /**
     * @return BelongsTo<TestInstrumentItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentItem::class, 'test_instrument_item_id');
    }
}
