<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOmrStudioStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    protected function prepareForValidation(): void
    {
        $overlay = (array) $this->input('overlay', []);
        $overlay['boxes'] = collect((array) ($overlay['boxes'] ?? []))
            ->map(static fn (mixed $box): mixed => is_array($box) ? ['page_no' => 1, ...$box] : $box)
            ->all();
        $studio = (array) $this->input('studio', []);
        foreach (['components', 'sections'] as $collection) {
            if (! isset($studio[$collection]) || ! is_array($studio[$collection])) {
                continue;
            }
            $studio[$collection] = collect($studio[$collection])
                ->map(static fn (mixed $item): mixed => is_array($item) ? ['page_no' => 1, ...$item] : $item)
                ->all();
        }
        $this->merge(['overlay' => $overlay, 'studio' => $studio]);
    }

    /** @var array<string, int> */
    private const STUDIO_STRING_FIELDS = [
        'markerPattern' => 32,
        'headerTitle' => 500,
        'headerSubtitle' => 500,
        'headerFontFamily' => 120,
        'headerColor' => 32,
        'headerAlign' => 16,
        'instructionsContent' => 5000,
        'instructionsFontFamily' => 120,
        'instructionsColor' => 32,
        'signatureContent' => 1000,
        'signatureFontFamily' => 120,
        'signatureColor' => 32,
        'metadataContent' => 1000,
        'htmlCode' => 2000000,
    ];

    private const STUDIO_NUMERIC_FIELDS = [
        'bubbleRadiusMm',
        'headerFontSize', 'headerX', 'headerY', 'headerW', 'headerH',
        'qrX', 'qrY', 'qrW', 'qrH',
        'instructionsFontSize', 'instX', 'instY', 'instW', 'instH',
        'signatureFontSize', 'sigX', 'sigY', 'sigW', 'sigH',
        'metadataFontSize',
    ];

    private const STUDIO_BOOLEAN_FIELDS = [
        'showHeaderOverlay', 'headerLocked',
        'showQrOverlay', 'qrLocked',
        'showInstructionsOverlay', 'instLocked',
        'showSignatureOverlay', 'sigLocked',
        'showRollOverlay',
    ];

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = [
            'overlay' => ['required', 'array'],
            'overlay.boxes' => ['present', 'array', 'max:100'],
            'overlay.boxes.*.tag' => ['required', 'string', 'max:64'],
            'overlay.boxes.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'overlay.boxes.*.scope' => ['required', 'string', 'max:32'],
            'overlay.boxes.*.x' => ['required', 'numeric', 'min:0'],
            'overlay.boxes.*.y' => ['required', 'numeric', 'min:0'],
            'overlay.boxes.*.width' => ['required', 'numeric', 'gt:0'],
            'overlay.boxes.*.height' => ['required', 'numeric', 'gt:0'],
            'overlay.boxes.*.overflow' => ['nullable', 'string', 'max:16'],
            'overlay.boxes.*.font_size' => ['nullable', 'numeric', 'min:1', 'max:120'],
            'overlay.boxes.*.content' => ['nullable', 'string', 'max:1000'],
            'overlay.boxes.*.name' => ['nullable', 'string', 'max:64'],
            'mapping' => ['nullable', 'array'],
            'mapping.document_fields' => ['sometimes', 'array', 'max:20'],
            'mapping.document_fields.*.id' => ['required', 'string', 'max:64'],
            'mapping.document_fields.*.type' => ['required', 'string', 'in:candidate_name,birthdate,room_seat,signature,custom_input'],
            'mapping.document_fields.*.label' => ['required', 'string', 'max:100'],
            'mapping.document_fields.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'mapping.document_fields.*.x_mm' => ['required', 'numeric', 'min:0', 'max:500'],
            'mapping.document_fields.*.y_mm' => ['required', 'numeric', 'min:0', 'max:500'],
            'mapping.document_fields.*.w_mm' => ['required', 'numeric', 'min:1', 'max:500'],
            'mapping.document_fields.*.h_mm' => ['required', 'numeric', 'min:1', 'max:500'],
            'mapping.page_width' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1'],
            'mapping.page_height' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1'],
            'mapping.page_count' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1', 'max:4'],
            'mapping.blocks' => ['nullable', 'array', 'max:1000'],
            'mapping.declared_inventory' => ['nullable', 'array', 'max:1000'],
            'mapping.declared_inventory.*' => ['array:page_no,area_code,question_count,option_labels'],
            'mapping.declared_inventory.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'mapping.declared_inventory.*.area_code' => ['nullable', 'string', 'max:100'],
            'mapping.declared_inventory.*.question_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mapping.declared_inventory.*.option_labels' => ['nullable', 'array', 'max:10'],
            'mapping.declared_inventory.*.option_labels.*' => ['string', 'max:32'],
            'mapping.identity' => ['nullable', 'array:method,strategy,has_bubbles,has_write_in,digit_count,regions,write_in_boxes,identity_pages'],
            'mapping.identity.identity_pages' => ['nullable', 'array', 'max:4'],
            'mapping.identity.identity_pages.*' => ['integer', 'min:1', 'max:4'],
            'mapping.identity.method' => ['nullable', 'string', Rule::in(['shaded_grid', 'write_in_manual', 'bubbles_and_write_in', 'bubbles_only', 'write_in_only'])],
            'mapping.identity.strategy' => ['nullable', 'string', Rule::in(['shaded_grid', 'write_in_manual', 'bubbles_and_write_in', 'bubbles_only', 'write_in_only'])],
            'mapping.identity.has_bubbles' => ['nullable', 'boolean'],
            'mapping.identity.has_write_in' => ['nullable', 'boolean'],
            'mapping.identity.digit_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'mapping.identity.regions' => ['nullable', 'array', 'max:4'],
            'mapping.identity.regions.*' => ['array:page_no,x,y,w,h'],
            'mapping.identity.regions.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'mapping.identity.regions.*.x' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.regions.*.y' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.regions.*.w' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.regions.*.h' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.write_in_boxes' => ['nullable', 'array', 'max:4'],
            'mapping.identity.write_in_boxes.*' => ['array:page_no,x_mm,y_mm,w_mm,h_mm'],
            'mapping.identity.write_in_boxes.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'mapping.identity.write_in_boxes.*.x_mm' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.write_in_boxes.*.y_mm' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.write_in_boxes.*.w_mm' => ['nullable', 'numeric', 'min:0'],
            'mapping.identity.write_in_boxes.*.h_mm' => ['nullable', 'numeric', 'min:0'],
            'studio' => ['nullable', 'array'],
            'studio.document_fields' => ['sometimes', 'array', 'max:20'],
            'studio.document_fields.*.id' => ['required', 'string', 'max:64'],
            'studio.document_fields.*.type' => ['required', 'string', 'in:candidate_name,birthdate,room_seat,signature,custom_input'],
            'studio.document_fields.*.label' => ['required', 'string', 'max:100'],
            'studio.document_fields.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.document_fields.*.x_mm' => ['required', 'numeric', 'min:0', 'max:500'],
            'studio.document_fields.*.y_mm' => ['required', 'numeric', 'min:0', 'max:500'],
            'studio.document_fields.*.w_mm' => ['required', 'numeric', 'min:1', 'max:500'],
            'studio.document_fields.*.h_mm' => ['required', 'numeric', 'min:1', 'max:500'],
            'studio.current_stage' => ['nullable', 'integer', 'between:1,4'],
            'studio.active_page_no' => ['nullable', 'integer', 'min:1', 'max:4'],
            'studio.stage1Mode' => ['nullable', 'string', Rule::in(['html', 'image', 'upload'])],
            'studio.baseType' => ['nullable', 'string', Rule::in(OmrTemplateAuthoringDraft::SOURCE_KINDS)],
            'studio.sections' => ['nullable', 'array', 'max:1000'],
            'studio.sections.*' => ['array'],
            'studio.declaredInventory' => ['nullable', 'array', 'max:1000'],
            'studio.declaredInventory.*' => ['array:id,page_no,area_code,question_count,option_labels,geometry_id'],
            'studio.declaredInventory.*.id' => ['nullable', 'string', 'max:100'],
            'studio.declaredInventory.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.declaredInventory.*.area_code' => ['nullable', 'string', 'max:100'],
            'studio.declaredInventory.*.question_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'studio.declaredInventory.*.option_labels' => ['nullable', 'array', 'max:10'],
            'studio.declaredInventory.*.option_labels.*' => ['string', 'max:32'],
            'studio.declaredInventory.*.geometry_id' => ['nullable', 'string', 'max:100'],
            'studio.physicalInventory' => ['nullable', 'array:revision,sections'],
            'studio.physicalInventory.revision' => ['required', 'integer', 'min:0'],
            'studio.physicalInventory.sections' => ['required', 'array', 'max:100'],
            'studio.physicalInventory.sections.*' => ['array:printed_section_uid,native_code,name,page_no,numbering,expected_item_count,display_order,items'],
            'studio.physicalInventory.sections.*.printed_section_uid' => ['required', 'string', 'max:64'],
            'studio.physicalInventory.sections.*.native_code' => ['nullable', 'string', 'max:64'],
            'studio.physicalInventory.sections.*.name' => ['required', 'string', 'max:100'],
            'studio.physicalInventory.sections.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.physicalInventory.sections.*.numbering' => ['required', 'array:start,step,prefix,suffix'],
            'studio.physicalInventory.sections.*.numbering.start' => ['required', 'integer', 'min:1', 'max:100000'],
            'studio.physicalInventory.sections.*.numbering.step' => ['required', 'integer', 'min:1', 'max:1000'],
            'studio.physicalInventory.sections.*.numbering.prefix' => ['nullable', 'string', 'max:32'],
            'studio.physicalInventory.sections.*.numbering.suffix' => ['nullable', 'string', 'max:32'],
            'studio.physicalInventory.sections.*.expected_item_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'studio.physicalInventory.sections.*.display_order' => ['required', 'integer', 'min:1', 'max:100'],
            'studio.physicalInventory.sections.*.items' => ['required', 'array', 'max:1000'],
            'studio.physicalInventory.sections.*.items.*' => ['array:physical_item_uid,native_label,page_no,printed_number,option_tokens,guide_id'],
            'studio.physicalInventory.sections.*.items.*.physical_item_uid' => ['required', 'string', 'max:64'],
            'studio.physicalInventory.sections.*.items.*.native_label' => ['nullable', 'string', 'max:100'],
            'studio.physicalInventory.sections.*.items.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.physicalInventory.sections.*.items.*.printed_number' => ['required', 'integer', 'min:1', 'max:100000'],
            'studio.physicalInventory.sections.*.items.*.option_tokens' => ['required', 'array', 'max:10'],
            'studio.physicalInventory.sections.*.items.*.option_tokens.*' => ['string', 'max:32'],
            'studio.physicalInventory.sections.*.items.*.guide_id' => ['nullable', 'string', 'max:100'],
            'studio.physicalInventoryConfirmation' => ['nullable', 'array:revision,digest'],
            'studio.physicalInventoryConfirmation.revision' => ['required_with:studio.physicalInventoryConfirmation', 'integer', 'min:0'],
            'studio.physicalInventoryConfirmation.digest' => ['required_with:studio.physicalInventoryConfirmation', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'studio.identity' => ['nullable', 'array:method,strategy,has_bubbles,has_write_in,digit_count,regions,write_in_boxes,identity_pages'],
            'studio.identity.identity_pages' => ['nullable', 'array', 'max:4'],
            'studio.identity.identity_pages.*' => ['integer', 'min:1', 'max:4'],
            'studio.identity.method' => ['nullable', 'string', Rule::in(['shaded_grid', 'write_in_manual', 'bubbles_and_write_in', 'bubbles_only', 'write_in_only'])],
            'studio.identity.strategy' => ['nullable', 'string', Rule::in(['shaded_grid', 'write_in_manual', 'bubbles_and_write_in', 'bubbles_only', 'write_in_only'])],
            'studio.identity.has_bubbles' => ['nullable', 'boolean'],
            'studio.identity.has_write_in' => ['nullable', 'boolean'],
            'studio.identity.digit_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'studio.identity.regions' => ['nullable', 'array', 'max:4'],
            'studio.identity.regions.*' => ['array:page_no,x,y,w,h'],
            'studio.identity.regions.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.identity.regions.*.x' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.regions.*.y' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.regions.*.w' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.regions.*.h' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.write_in_boxes' => ['nullable', 'array', 'max:4'],
            'studio.identity.write_in_boxes.*' => ['array:page_no,x_mm,y_mm,w_mm,h_mm'],
            'studio.identity.write_in_boxes.*.page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'studio.identity.write_in_boxes.*.x_mm' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.write_in_boxes.*.y_mm' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.write_in_boxes.*.w_mm' => ['nullable', 'numeric', 'min:0'],
            'studio.identity.write_in_boxes.*.h_mm' => ['nullable', 'numeric', 'min:0'],
            'studio.components' => ['nullable', 'array', 'max:1000'],
            'studio.reviewed_crop_asset_id' => ['nullable', 'integer', 'min:1'],
            'studio.components.*' => ['array'],
            'studio.customTextItems' => ['nullable', 'array', 'max:100'],
            'studio.palette_filter' => ['nullable', 'array'],
            'studio.marginMm' => ['nullable', 'numeric', 'min:6', 'max:35'],
            'studio.smartSnapSearchMm' => ['nullable', 'numeric', 'min:2', 'max:12'],
            'studio.rollDigits' => ['nullable', 'integer', 'between:1,20'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'source_kind' => ['nullable', 'string', Rule::in(OmrTemplateAuthoringDraft::SOURCE_KINDS)],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];

        foreach (self::STUDIO_STRING_FIELDS as $field => $maximumLength) {
            $rules["studio.{$field}"] = ['nullable', 'string', "max:{$maximumLength}"];
        }
        foreach (self::STUDIO_NUMERIC_FIELDS as $field) {
            $rules["studio.{$field}"] = ['nullable', 'numeric'];
        }
        foreach (self::STUDIO_BOOLEAN_FIELDS as $field) {
            $rules["studio.{$field}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
