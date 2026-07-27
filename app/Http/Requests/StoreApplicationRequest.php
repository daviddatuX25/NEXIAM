<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $activeAcademicYear = AcademicYear::active();
        $isFlexible = (bool) \App\Models\SystemSetting::get('allow_flexible_applications', false) || auth()->check();

        $emailRules = $isFlexible ? ['nullable', 'email'] : ['required', 'email'];
        if ($activeAcademicYear && !empty($this->input('email'))) {
            $emailRules[] = Rule::unique('applications', 'email')->where('academic_year_id', $activeAcademicYear->id);
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birthdate' => $isFlexible 
                ? ['nullable', 'date', 'before:today', 'after:-100 years'] 
                : ['required', 'date', 'before:-15 years', 'after:-50 years'],
            'sex' => $isFlexible 
                ? ['nullable', 'string', 'in:male,female'] 
                : ['required', 'string', 'in:male,female'],
            'applicant_type' => $isFlexible 
                ? ['nullable', 'string', 'in:new,transferee'] 
                : ['required', 'string', 'in:new,transferee'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:100'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:12'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'gwa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'course_preference_1' => $isFlexible 
                ? ['nullable', 'integer', 'exists:courses,id'] 
                : ['required', 'integer', 'exists:courses,id'],
            'course_preference_2' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1'],
            'course_preference_3' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1', 'different:course_preference_2'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'accept_immediately' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An application with this email address already exists for the current academic year.',
        ];
    }
}
