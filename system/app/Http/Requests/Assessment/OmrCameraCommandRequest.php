<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OmrCameraCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol_version' => ['required', 'string', 'in:nexiam.omr.camera.v1'],
            'command' => ['required', Rule::in(['SET_CONFIG', 'CAPTURE', 'PAUSE', 'STOP'])],
            'command_id' => ['required', 'uuid'],
            'operation_id' => ['required_if:command,CAPTURE', 'prohibited_unless:command,CAPTURE', 'uuid'],
            'config_revision' => ['required', 'integer', 'min:1'], 'source_generation' => ['required', 'integer', 'min:1'],
            'target' => ['required_if:command,CAPTURE', 'nullable', 'array:workbenchId,clientCaptureId,pageNo,captureRevision,instanceId,sheetId,targetKey'],
            'target.workbenchId' => ['required_if:command,CAPTURE', 'nullable', 'string', 'max:180'],
            'target.clientCaptureId' => ['required_if:command,CAPTURE', 'nullable', 'string', 'max:128'],
            'target.pageNo' => ['required_if:command,CAPTURE', 'nullable', 'integer', 'between:1,4'],
            'target.captureRevision' => ['present_with:target', 'nullable', 'integer', 'min:0'],
            'target.instanceId' => ['present_with:target', 'nullable', 'string', 'max:128'],
            'target.sheetId' => ['present_with:target', 'nullable', 'integer', 'min:1'],
            'target.targetKey' => ['required_if:command,CAPTURE', 'nullable', 'string', 'max:512'],
            'target_key' => ['nullable', 'string', 'max:512'],
            'trigger' => ['sometimes', Rule::in(['shutter', 'keyboard', 'steady', 'qr_capture'])],
            'mode' => ['sometimes', Rule::in(['manual', 'auto', 'batch'])],
            'config' => ['required_if:command,SET_CONFIG,PAUSE', 'array:registrationMode,registration_mode,pageCount,page_count,mode,batchTrigger,batch_trigger,steadyDelayMs,steady_delay_ms,qrTriggerEnabled,qr_trigger_enabled,paused', 'min:1'],
            'config.registrationMode' => ['sometimes', Rule::in(['fiducial_anchored', 'document_bound'])],
            'config.registration_mode' => ['sometimes', Rule::in(['fiducial_anchored', 'document_bound'])],
            'config.pageCount' => ['sometimes', 'integer', 'between:1,4'],
            'config.page_count' => ['sometimes', 'integer', 'between:1,4'],
            'config.mode' => ['sometimes', Rule::in(['manual', 'auto', 'batch'])],
            'config.batchTrigger' => ['sometimes', Rule::in(['auto_steady', 'manual_qr'])],
            'config.batch_trigger' => ['sometimes', Rule::in(['auto_steady', 'manual_qr'])],
            'config.steadyDelayMs' => ['sometimes', Rule::in([150, 300, 500, 800])],
            'config.steady_delay_ms' => ['sometimes', Rule::in([150, 300, 500, 800])],
            'config.qrTriggerEnabled' => ['sometimes', 'boolean'],
            'config.qr_trigger_enabled' => ['sometimes', 'boolean'],
            'config.paused' => ['sometimes', 'boolean'],
        ];
    }
}
