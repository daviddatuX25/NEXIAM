<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class OmrCameraHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->header('X-Omr-Camera-Role') !== 'node') {
            return [
                'protocol_version' => ['required', 'string', 'in:nexiam.omr.camera.v1'],
                'ready' => ['required', 'boolean'],
                'sequence' => ['required', 'integer', 'between:1,2147483647'],
                'source_generation' => ['required', 'integer', 'min:1'],
                'target_key' => ['required', 'string', 'max:512'],
            ];
        }

        return [
            'protocol_version' => ['required', 'string', 'in:nexiam.omr.camera.v1'],
            'kind' => ['required', 'string', 'in:quality,qr_intent,qr_card_removed,heartbeat'],
            'ready' => ['required_if:kind,quality', 'boolean'],
            'sequence' => ['required', 'integer', 'between:1,2147483647'],
            'frame_sequence' => ['required_if:kind,quality', 'integer', 'min:0'],
            'source_generation' => ['required', 'integer', 'min:1'],
            'config_revision' => ['required', 'integer', 'min:1'],
            'target_key' => ['nullable', 'string', 'max:512'],
            'lock_progress' => ['nullable', 'numeric', 'between:0,1'],
            'observed_at_age_ms' => ['required', 'integer', 'between:0,600000'],
            'quality' => ['required_if:kind,quality', 'array:in_bounds,angle_ok,steady_ok,lighting_ok'],
            'quality.in_bounds' => ['required_with:quality', 'boolean'],
            'quality.angle_ok' => ['required_with:quality', 'boolean'],
            'quality.steady_ok' => ['required_with:quality', 'boolean'],
            'quality.lighting_ok' => ['required_with:quality', 'boolean'],
            'result' => ['required_if:kind,quality', 'array:type,generation,frameId,capturedAt,quad,gates'],
            'result.capturedAt' => ['sometimes', 'numeric', 'between:0,2147483647'],
            'result.type' => ['required_with:result', 'in:analysis'],
            'result.generation' => ['required_with:result', 'integer', 'min:0'],
            'result.frameId' => ['required_with:result', 'integer', 'min:0'],
            'result.quad' => ['nullable', 'array', 'size:4'],
            'result.quad.*' => ['required_with:result.quad', 'array:x,y'],
            'result.quad.*.x' => ['required_with:result.quad.*', 'numeric', 'between:0,1'],
            'result.quad.*.y' => ['required_with:result.quad.*', 'numeric', 'between:0,1'],
            'result.gates' => ['required_with:result', 'array:in_bounds,angle_ok,steady_ok,lighting_ok'],
            'result.gates.in_bounds' => ['required_with:result.gates', 'boolean'],
            'result.gates.angle_ok' => ['required_with:result.gates', 'boolean'],
            'result.gates.steady_ok' => ['required_with:result.gates', 'boolean'],
            'result.gates.lighting_ok' => ['required_with:result.gates', 'boolean'],
            'qr_card_removed' => ['required_if:kind,qr_card_removed', 'boolean'],
            'command' => ['required_if:kind,qr_intent', 'nullable', 'in:CAPTURE,NEXT_PAGE,NEXT_SET,REDO_LAST'],
            'command_id' => ['required_if:kind,qr_intent', 'nullable', 'uuid'],
            'qr_supported' => ['sometimes', 'boolean'],
        ];
    }
}
