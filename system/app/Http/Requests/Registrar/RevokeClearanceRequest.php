<?php

namespace App\Http\Requests\Registrar;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RevokeClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');
        $actor = $this->user();

        return $attempt instanceof AdmissionAttempt
            && $actor instanceof User
            && app(AttemptRequirementChecklistBoundary::class)->allows(
                $actor,
                $attempt,
                CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN,
            );
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
