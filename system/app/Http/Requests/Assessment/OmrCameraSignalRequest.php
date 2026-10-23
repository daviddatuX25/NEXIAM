<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OmrCameraSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['offer', 'answer', 'ice'])],
            'generation' => ['required', 'integer', 'min:1'], 'payload' => ['required', 'array', 'max:12'],
            'payload.type' => ['nullable', 'string', Rule::in(['offer', 'answer', 'pranswer', 'rollback'])],
            'payload.sdp' => ['nullable', 'string', 'max:60000'], 'payload.candidate' => ['nullable', 'string', 'max:4096'],
            'payload.sdpMid' => ['nullable', 'string', 'max:64'], 'payload.sdpMLineIndex' => ['nullable', 'integer', 'min:0'],
            'payload.usernameFragment' => ['nullable', 'string', 'max:256'],
        ];
    }
}
