<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrAnswerSheetPage extends Model
{
    protected $fillable = [
        'template_version_id',
        'page_no',
        'width',
        'height',
        'page_manifest_json',
        'page_manifest_hash',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $page): void {
            $page->assertCompiledParentMutable();
        });

        static::deleting(static function (self $page): void {
            $page->assertCompiledParentMutable();
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
            throw new \RuntimeException('Compiled answer-sheet pages are immutable.');
        }
    }

    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'page_manifest_json' => 'array',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }
}
