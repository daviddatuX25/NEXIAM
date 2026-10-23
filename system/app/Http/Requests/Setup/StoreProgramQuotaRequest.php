<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgramQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $programId = $this->input('program_id');
        $cycleId = $this->input('academic_cycle_id');

        if (! config('admission.quota_writes_enabled', false)
            || $user === null
            || ! app(CapabilityAuthorizer::class)->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
        }

        if ($programId && $cycleId) {
            $program = Program::find($programId);
            $cycle = AcademicCycle::find($cycleId);

            if ($program && $cycle) {
                return app(GovernanceAuthorizer::class)->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true);
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'slots' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
