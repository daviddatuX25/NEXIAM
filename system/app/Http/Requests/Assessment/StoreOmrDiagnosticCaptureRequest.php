<?php

namespace App\Http\Requests\Assessment;

use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

use function is_array;

final class StoreOmrDiagnosticCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:25600', 'required_without:staged_frame_id', 'prohibits:staged_frame_id'],
            'staged_frame_id' => ['sometimes', 'uuid', 'prohibits:image'],
            'camera_command_id' => ['sometimes', 'uuid', 'required_with:staged_frame_id', Rule::prohibitedIf(! $this->filled('staged_frame_id'))],
            'pairing_id' => ['sometimes', 'uuid', 'required_with:staged_frame_id', Rule::prohibitedIf(! $this->filled('staged_frame_id'))],
            'operation_id' => ['required', 'uuid'],
            'source_generation' => ['required', 'integer', 'min:1'],
            'config_revision' => ['required', 'integer', 'min:1'],
            'target_key' => ['required', 'string', 'min:1', 'max:512'],
            'client_capture_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'crop_policy' => ['sometimes', 'string', Rule::in(['auto_document_quad', 'keep_original'])],
            'instance_id' => ['sometimes', 'string', 'regex:/^instance-00[1-3]$/'],
            'target_revision' => ['sometimes', 'integer', 'min:1'],
            'pending_capture_id' => ['sometimes', 'uuid'],
            'pending_capture_revision' => ['sometimes', 'integer', 'min:1'],
            'replacement_reason' => ['sometimes', 'string', 'min:1', 'max:500'],
            'client_quad' => ['sometimes', 'array:points,width,height,orientation,input_hash,page_no'],
            'client_quad.points' => ['required_with:client_quad', 'array', 'list', 'size:4'],
            'client_quad.points.*' => ['required', 'array', 'list', 'size:2'],
            'client_quad.points.*.*' => ['required', 'numeric', 'between:0,1'],
            'client_quad.width' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.height' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.orientation' => ['required_with:client_quad', 'integer', 'between:1,8'],
            'client_quad.input_hash' => ['required_with:client_quad', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'client_quad.page_no' => ['required_with:client_quad', 'integer', 'between:1,4'],
            'template_version_id' => ['prohibited'],
            'template_id' => ['prohibited'],
            'expected_pages' => ['prohibited'],
            'pages' => ['prohibited'],
            'page_count' => ['prohibited'],
            'active_page_no' => ['prohibited'],
            'registration_mode' => ['prohibited'],
            'qr_mode' => ['prohibited'],
            'copy_policy' => ['prohibited'],
            'context' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'session_id' => ['prohibited'],
            'answer_key' => ['prohibited'],
            'expected_vector' => ['prohibited'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $value = $this->input('client_quad');
                if (! is_array($value) || is_string($this->input('staged_frame_id')) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $file = $this->file('image');
                    if ($file === null || $file->getMimeType() === 'application/pdf') {
                        $validator->errors()->add('client_quad', 'Advisory corners require a single raster image.');

                        return;
                    }
                    $quad = OmrClientQuad::fromArray($value);
                    $sourceImage = OmrSourceImage::fromBytes((string) $file->get());
                    $pageNo = (int) $this->input('page_no');
                    if (! $quad->isBoundTo($sourceImage, $pageNo)) {
                        $validator->errors()->add('client_quad', 'The advisory corners must be bound to the uploaded image and page.');
                    }
                } catch (Throwable) {
                    $validator->errors()->add('client_quad', 'The advisory corners are malformed or do not match the uploaded image.');
                }
            },
        ];
    }
}
