<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date', 'before:-15 years', 'after:-50 years'],
            'sex' => ['nullable', 'string', 'in:male,female'],
            'applicant_type' => ['nullable', 'string', 'in:new,transferee'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:12'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'gwa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'course_preference_1' => ['nullable', 'integer', 'exists:courses,id'],
            'course_preference_2' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1'],
            'course_preference_3' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1', 'different:course_preference_2'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'status' => ['nullable', 'string', 'in:pending,accepted,dismissed'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
