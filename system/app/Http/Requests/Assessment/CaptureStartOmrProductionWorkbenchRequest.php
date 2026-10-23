<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class CaptureStartOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:25600', 'required_without_all:image_bytes,staged_frame_id'],
            'image_bytes' => ['nullable', 'string', 'required_without_all:image,staged_frame_id'],
            'mime' => ['nullable', 'string', 'max:100', 'required_with:image_bytes', 'prohibits:staged_frame_id'],
            'staged_frame_id' => ['sometimes', 'uuid', 'prohibits:image,image_bytes,mime'],
            'camera_command_id' => ['sometimes', 'uuid', 'required_with:staged_frame_id', Rule::prohibitedIf(! $this->filled('staged_frame_id'))],
            'pairing_id' => ['sometimes', 'uuid', 'required_with:staged_frame_id', Rule::prohibitedIf(! $this->filled('staged_frame_id'))],
            'operation_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'source_generation' => ['required', 'integer', 'min:1'],
            'config_revision' => ['required', 'integer', 'min:1'],
            'target_key' => ['required', 'string', 'min:1', 'max:512'],
            'target_revision' => ['sometimes', 'integer', 'min:1'],
            'instance_id' => ['prohibited'],
            'capture_mode' => ['required', 'string', Rule::in(['auto', 'manual'])],
            'sheet_id' => ['sometimes', 'integer', 'min:1'],
            'crop_policy' => ['sometimes', 'string', Rule::in(['auto_document_quad', 'keep_original'])],
            'client_capture_id' => ['required', 'uuid'],
            'page_no' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'pending_capture_id' => ['sometimes', 'uuid'],
            'pending_capture_revision' => ['sometimes', 'integer', 'min:1'],
            'replacement_reason' => ['sometimes', 'string', 'min:1', 'max:500'],
            'template_version_id' => ['prohibited'],
            'expected_pages' => ['prohibited'],
            'qr_payload' => ['nullable', 'string', 'max:300'],
            'qr_state' => ['nullable', 'string', Rule::in(['absent', 'undecodable', 'present_invalid'])],
            'display_code' => ['nullable', 'string', 'max:24'],
            'manual_confirmed' => ['nullable', 'boolean'],
            'observed_set_code' => ['nullable', 'string', Rule::in(['A', 'B', 'C', 'D'])],
            'client_quad' => ['sometimes', 'array:points,width,height,orientation,input_hash,page_no'],
            'client_quad.points' => ['required_with:client_quad', 'array', 'list', 'size:4'],
            'client_quad.points.*' => ['required', 'array', 'list', 'size:2'],
            'client_quad.points.*.*' => ['required', 'numeric', 'between:0,1'],
            'client_quad.width' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.height' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.orientation' => ['required_with:client_quad', 'integer', 'between:1,8'],
            'client_quad.input_hash' => ['required_with:client_quad', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'client_quad.page_no' => ['required_with:client_quad', 'integer', 'between:1,4'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasImage = $this->file('image') !== null;
                $hasImageBytes = is_string($this->input('image_bytes'));
                $hasStagedFrame = is_string($this->input('staged_frame_id'));
                if (($hasImage && $hasImageBytes)
                    || (($hasImage || $hasImageBytes) && $hasStagedFrame)
                    || (! $hasStagedFrame && ! $hasImage && ! $hasImageBytes)) {
                    $validator->errors()->add('image', 'Provide exactly one raw image or staged camera frame.');
                }
            },
            function (Validator $validator): void {
                $value = $this->input('client_quad');
                if (! is_array($value) || is_string($this->input('staged_frame_id')) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $file = $this->file('image');
                    if ($file !== null) {
                        $bytes = (string) $file->get();
                        $mime = (string) $file->getMimeType();
                    } else {
                        $bytes = $this->input('image_bytes');
                        $mime = $this->input('mime');
                    }
                    if (! is_string($bytes) || ! is_string($mime)
                        || ! in_array(strtolower(trim($mime)), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        $validator->errors()->add('client_quad', 'Advisory corners require a single raster image.');

                        return;
                    }
                    $mime = strtolower(trim($mime));
                    if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
                        $validator->errors()->add('client_quad', 'The advisory corners must match the uploaded image.');

                        return;
                    }

                    $quad = OmrClientQuad::fromArray($value);
                    $sourceImage = OmrSourceImage::fromBytes($bytes);
                    if (! $quad->isBoundTo($sourceImage, (int) $this->input('page_no', 1))) {
                        $validator->errors()->add('client_quad', 'The advisory corners must be bound to the uploaded image and selected page.');
                    }
                } catch (Throwable) {
                    $validator->errors()->add('client_quad', 'The advisory corners are malformed or do not match the uploaded image.');
                }
            },
        ];
    }
}
