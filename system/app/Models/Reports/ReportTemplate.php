<?php

namespace App\Models\Reports;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplate extends Model
{
    public const MODE_HTML = 'html';

    public const SOURCE_BUILDER = 'builder';

    public const SOURCE_DOCX = 'docx';

    public const SOURCE_HTML = 'html';

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'field_policy_version' => 1,
        'lock_version' => 1,
    ];

    protected $fillable = [
        'name',
        'surface_key',
        'office',
        'institution_id',
        'grain',
        'mounted_slots',
        'selected_leaves',
        'custom_group',
        'mode',
        'paper_size',
        'orientation',
        'document_path',
        'document_checksum',
        'document_extension',
        'content',
        'source_kind',
        'builder_schema_version',
        'builder_json',
        'compiled_html',
        'compiler_version',
        'field_policy_version',
        'lock_version',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'mounted_slots' => 'array',
            'selected_leaves' => 'array',
            'builder_json' => 'array',
            'builder_schema_version' => 'integer',
            'field_policy_version' => 'integer',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
            'created_by_user_id' => 'integer',
        ];
    }

    public function sourceChecksum(): string
    {
        $builderJson = $this->canonicalizeBuilderJson($this->builder_json);
        $renderedContent = $this->compiled_html ?? $this->content;

        return hash('sha256', json_encode([
            'surface' => $this->surface_key,
            'mode' => $this->mode,
            'source_kind' => $this->source_kind,
            'builder_schema_version' => $this->builder_schema_version,
            'compiler_version' => $this->compiler_version,
            'builder_json_sha256' => $builderJson === null
                ? null
                : hash('sha256', json_encode($builderJson, JSON_THROW_ON_ERROR)),
            'rendered_content_sha256' => $renderedContent === null
                ? null
                : hash('sha256', $renderedContent),
            'document_checksum' => $this->document_checksum,
            'paper_size' => $this->paper_size,
            'orientation' => $this->orientation,
        ], JSON_THROW_ON_ERROR));
    }

    public function executionContractChecksum(): string
    {
        return hash('sha256', json_encode([
            'source_checksum' => $this->sourceChecksum(),
            'grain' => $this->grain,
            'selected_leaves' => $this->selected_leaves,
            'field_policy_version' => $this->field_policy_version,
        ], JSON_THROW_ON_ERROR));
    }

    private function canonicalizeBuilderJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeBuilderJson($item);
        }

        return $value;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
