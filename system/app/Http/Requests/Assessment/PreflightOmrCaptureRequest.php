<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class PreflightOmrCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same image limits as the ordinary sheet upload path.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required_without:staged_frame_id', 'prohibits:staged_frame_id,pairing_id,camera_command_id', 'file', 'mimes:jpeg,png,webp', 'max:25600'],
            'staged_frame_id' => ['required_without:image', 'prohibits:image', 'uuid'],
            'pairing_id' => ['required_with:staged_frame_id', 'prohibits:image', 'uuid'],
            'camera_command_id' => ['required_with:staged_frame_id', 'prohibits:image', 'uuid'],
        ];
    }
}
