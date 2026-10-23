<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use Illuminate\Foundation\Http\FormRequest;

class BulkStoreProgramQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $cycleId = $this->input('academic_cycle_id');
        $members = $this->input('quotas');
        if (! config('admission.quota_writes_enabled', false)
            || $user === null
            || ! app(CapabilityAuthorizer::class)->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)
            || ! is_numeric($cycleId)
            || ! is_array($members)) {
            return false;
        }

        $cycle = AcademicCycle::query()->find($cycleId);
        if ($cycle === null) {
            return false;
        }
        $governance = app(GovernanceAuthorizer::class);
        foreach ($members as $member) {
            if (! is_array($member) || ! isset($member['program_id']) || ! is_numeric($member['program_id'])) {
                continue;
            }
            $program = Program::query()->find($member['program_id']);
            if ($program !== null && ! $governance->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'idempotency_key' => ['required', 'uuid'],
            'quotas' => ['required', 'array', 'min:1'],
            'quotas.*.program_id' => ['required', 'integer', 'distinct', 'exists:programs,id'],
            'quotas.*.slots' => ['required', 'integer', 'min:0', 'max:100000'],
            'quotas.*.reason' => ['required', 'string', 'min:1', 'max:1000'],
            'quotas.*.expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
