<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class OmrCameraStageFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frame' => ['required', 'file', 'mimetypes:image/jpeg,image/png', 'max:20480'],
            'command_id' => ['required', 'uuid'], 'frame_id' => ['required', 'uuid'],
            'target_key' => ['required', 'string', 'max:512'], 'client_quad' => ['nullable', 'json', 'max:2048'],
        ];
    }
}
