<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Auth\Support\StaffSurface;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffProfileNameRequest extends FormRequest
{
    public function authorize(StaffSurface $staffSurface): bool
    {
        return $staffSurface->allows($this);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
