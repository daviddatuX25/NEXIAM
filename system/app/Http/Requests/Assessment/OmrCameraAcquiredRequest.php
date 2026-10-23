<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OmrCameraAcquiredRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol_version' => ['required', 'string', 'in:nexiam.omr.camera.v1'],
            'command_id' => ['required', 'uuid'], 'status' => ['required', Rule::in(['applied', 'acquired', 'rejected'])],
            'source_generation' => ['required', 'integer', 'min:1'],
            'config_revision' => ['required', 'integer', 'min:1'],
            'target_key' => ['required_if:status,acquired', 'nullable', 'string', 'max:512'],
            'input_hash' => ['required_if:status,acquired', 'regex:/^[a-f0-9]{64}$/'],
            'mime' => ['required_if:status,acquired', Rule::in(['image/jpeg', 'image/png'])],
            'byte_count' => ['required_if:status,acquired', 'integer', 'min:1', 'max:20971520'],
            'width' => ['required_if:status,acquired', 'integer', 'between:1,12000'],
            'height' => ['required_if:status,acquired', 'integer', 'between:1,12000'],
            'orientation' => ['required_if:status,acquired', 'integer', 'between:1,8'],
            'reason' => ['required_if:status,rejected', 'string', 'max:512'],
        ];
    }
}
