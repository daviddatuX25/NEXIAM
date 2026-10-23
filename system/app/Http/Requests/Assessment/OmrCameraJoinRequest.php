<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class OmrCameraJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['pair_code' => ['required', 'string', 'size:6', 'regex:/^[A-Za-z0-9]+$/']];
    }
}
