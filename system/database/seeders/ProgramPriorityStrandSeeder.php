<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Support\PhilippineDegreeHollandMap;
use App\Foundation\Actions\RecordAuditEvent;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ProgramPriorityStrandSeeder extends Seeder
{
    /**
     * Seeds and updates CHED CMO priority strands for all active undergraduate programs.
     * Grounded in CHED Policies, Standards and Guidelines (CMOs) and DepEd CGP frameworks.
     */
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        if ($institution === null) {
            return;
        }

        $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first()
            ?? AcademicCycle::query()->where('institution_id', $institution->id)->first();

        if ($cycle === null) {
            return;
        }
        $baseSetId = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->benchmark_set_id;
        if ($baseSetId === null) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
            ?? User::query()->where('email', 'guidance.lead@nexiam.test')->first()
            ?? User::query()->where('email', 'guidance@nexiam.test')->first()
            ?? User::query()->whereHas('staffProfile', fn ($q) => $q->where('office', 'guidance'))->first();
        if ($actor === null) {
            return;
        }

        $catalog = app(TagudinUndergraduateCatalog::class);
        $programs = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->whereNotIn('code', $catalog->retiredCodes())
            ->get();

        $saveDraft = app(SaveProgramBenchmarkDraft::class);
        $updatedCount = 0;

        foreach ($programs as $program) {
            $priorityStrands = PhilippineDegreeHollandMap::priorityStrandsFor($program->code);
            if ($priorityStrands === []) {
                continue;
            }

            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $institution->id)
                ->where('program_id', $program->id)
                ->where('academic_cycle_id', $cycle->id)
                ->first();

            if ($draft !== null && $draft->current_version > 0) {
                $currentRevision = $draft->currentRevision();
                if ($currentRevision !== null) {
                    $content = $currentRevision->content;
                    $existingStrands = $content['authoring']['preferred_strands'] ?? [];
                    if (empty($existingStrands)) {
                        $content['authoring']['preferred_strands'] = $priorityStrands;
                        $saveDraft->handle(
                            $actor,
                            $program,
                            $cycle,
                            $content,
                            (int) $draft->current_version,
                            'Seed authoritative CHED CMO priority strands.',
                            'priority-strands-seed-'.$program->code
                        );
                        $updatedCount++;
                    }
                }
            } else {
                // Initialize clean draft baseline with priority strands
                $content = [
                    'tiers' => [
                        '1' => ['values' => ['gwa' => 92.0, 'LOGIC' => 88.0, 'PROFILE_VERBAL' => 85.0, 'PROFILE_QUANT' => 88.0, 'SPATIAL' => 80.0, 'MEMORY' => 80.0, 'LANGUAGE' => 75.0]],
                        '2' => ['values' => ['gwa' => 87.0, 'LOGIC' => 80.0, 'PROFILE_VERBAL' => 80.0, 'PROFILE_QUANT' => 80.0, 'SPATIAL' => 75.0, 'MEMORY' => 75.0, 'LANGUAGE' => 70.0]],
                        '3' => ['values' => ['gwa' => 83.0, 'LOGIC' => 75.0, 'PROFILE_VERBAL' => 72.0, 'PROFILE_QUANT' => 75.0, 'SPATIAL' => 70.0, 'MEMORY' => 70.0, 'LANGUAGE' => 68.0]],
                        '4' => ['values' => ['gwa' => 86.0, 'LOGIC' => 80.0, 'PROFILE_VERBAL' => 78.0, 'PROFILE_QUANT' => 82.0, 'SPATIAL' => 74.0, 'MEMORY' => 74.0, 'LANGUAGE' => 70.0]],
                        '5' => ['values' => ['gwa' => 78.0, 'LOGIC' => 60.0, 'PROFILE_VERBAL' => 62.0, 'PROFILE_QUANT' => 60.0, 'SPATIAL' => 60.0, 'MEMORY' => 60.0, 'LANGUAGE' => 58.0]],
                    ],
                    'authoring' => [
                        'version' => 2,
                        'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []],
                        'preferred_strands' => $priorityStrands,
                        'interests' => [],
                    ],
                ];

                $saveDraft->handle(
                    $actor,
                    $program,
                    $cycle,
                    $content,
                    0,
                    'Seed baseline CHED CMO priority strands.',
                    'priority-strands-init-'.$program->code
                );
                $updatedCount++;
            }
        }

        app(RecordAuditEvent::class)->handle('academic.priority_strands_seeded', [
            'institution_code' => $institution->code,
            'cycle_code' => $cycle->code,
            'programs_updated' => $updatedCount,
        ], 'seeder');
    }
}
