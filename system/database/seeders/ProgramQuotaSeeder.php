<?php

namespace Database\Seeders;

use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use Illuminate\Database\Seeder;

class ProgramQuotaSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = [40, 45, 50, 60];

        Institution::query()->with(['academicCycles' => fn ($query) => $query->orderBy('id')])->orderBy('id')->each(
            function (Institution $institution) use ($capacities): void {
                $programs = Program::query()
                    ->where('institution_id', $institution->id)
                    ->active()
                    ->admissionOffered()
                    ->orderBy('id')
                    ->get(['id', 'institution_id', 'code']);

                foreach ($institution->academicCycles as $cycle) {
                    foreach ($programs as $index => $program) {
                        ProgramQuota::query()->firstOrCreate(
                            ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
                            [
                                'slots' => $capacities[$index % count($capacities)],
                                'last_notice' => 'Baseline institutional cycle quota allocation.',
                            ],
                        );
                    }
                }
            },
        );
    }
}
