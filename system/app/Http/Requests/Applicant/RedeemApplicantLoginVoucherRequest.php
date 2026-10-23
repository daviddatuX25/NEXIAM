<?php

declare(strict_types=1);

namespace App\Http\Requests\Applicant;

use Illuminate\Foundation\Http\FormRequest;

class RedeemApplicantLoginVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'voucher_token' => ['required', 'string'],
        ];
    }
}
