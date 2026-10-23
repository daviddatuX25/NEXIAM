<?php

namespace App\Testing\Seeders\Stages;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeedSetupStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return DB::transaction(function (): array {
            $this->initContext();
            $programIds = Program::query()->where('institution_id', $this->institution->id)
                ->where('is_active', true)->where('is_admission_offered', true)->pluck('id');
            $checks = [
                ['key' => 'admin_office', 'label' => 'Active Institutional Admin Office lead', 'status' => User::query()
                    ->whereHas('staffProfile', fn ($query) => $query->where('institution_id', $this->institution->id)
                        ->where('office', 'admin')->where('status', StaffProfile::STATUS_ACTIVE)->whereNull('deactivated_at'))
                    ->get()->contains(fn (User $user): bool => app(CapabilityAuthorizer::class)
                    ->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $this->institution->id])) ? 'available' : 'missing',
                    'reason' => 'Institutional setup authority is anchored in the Admin Office.'],
                ['key' => 'cycle', 'label' => 'Current active academic cycle', 'status' => 'available', 'reason' => $this->cycle->staffLabel()],
                ['key' => 'window', 'label' => 'Open published intake window', 'status' => ApplicationWindow::query()
                    ->where('academic_cycle_id', $this->cycle->id)
                    ->where('status', 'published')->where('opens_at', '<=', now())->where('closes_at', '>=', now())->exists() ? 'available' : 'missing', 'reason' => 'Institutional dates and publication are preserved.'],
                ['key' => 'programs', 'label' => 'Active offered programs', 'status' => $programIds->isNotEmpty() ? 'available' : 'missing', 'reason' => 'Program titles and activation are not changed.'],
                ['key' => 'quotas', 'label' => 'Current-cycle program quotas', 'status' => $programIds->isNotEmpty()
                    && ProgramQuota::query()->where('academic_cycle_id', $this->cycle->id)->whereIn('program_id', $programIds)->count() === $programIds->count() ? 'available' : 'missing', 'reason' => 'Configured capacity is never reset.'],
                ['key' => 'rooms', 'label' => 'Active institutional exam rooms', 'status' => Room::query()
                    ->where('institution_id', $this->institution->id)->where('is_active', true)->get()->contains(fn (Room $room): bool => $room->isExamRoom()) ? 'available' : 'missing', 'reason' => 'Session-specific room, collision and seating checks still apply.'],
                ['key' => 'wayfinding_venues', 'label' => 'Active rooms with paired wayfinding coordinates', 'status' => Room::query()
                    ->where('institution_id', $this->institution->id)->where('is_active', true)
                    ->whereNotNull('latitude')->whereNotNull('longitude')->exists() ? 'available' : 'missing',
                    'reason' => 'Only active institutional rooms with both coordinates are wayfinding venues.'],
                ['key' => 'privacy', 'label' => 'Designated published intake privacy policy', 'status' => PublicPolicy::designatedIntake((int) $this->institution->getKey()) !== null ? 'available' : 'missing', 'reason' => 'New intake requires the institution-owned immutable PublicPolicy revision; legacy PrivacyPolicy records are not a fallback.'],
                ['key' => 'physical_materials', 'label' => 'Physical paper and device evidence', 'status' => 'unverified', 'reason' => 'A software setup check does not establish printed paper, device provenance or study readiness.'],
            ];

            return ['stage' => 'setup', 'set_id' => $this->set->id, 'count' => 0, 'attempts' => [],
                'prerequisites' => $checks, 'passed' => collect($checks)->where('status', 'available')->count(), 'total' => count($checks)];
        });
    }
}
