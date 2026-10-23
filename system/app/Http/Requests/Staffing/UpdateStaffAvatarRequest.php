<?php

declare(strict_types=1);

namespace App\Http\Requests\Staffing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->staffProfile()->exists();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:preset,upload,initials'],
            'preset' => ['required_if:type,preset', 'nullable', 'string', 'in:counselor,guidance_counselor,guidance_clerk,guidance_support,guidance_support_staff,registrar_admin,records,registrar_clerk,registrar_support,registrar_support_staff,program_head,dean,academic_exec,hr_officer,exam_proctor'],
            'photo' => ['required_if:type,upload', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
