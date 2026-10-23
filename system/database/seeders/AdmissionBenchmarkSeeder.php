<?php

namespace Database\Seeders;

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Database\Seeder;

class AdmissionBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Synthetic admission benchmark fixtures are unavailable in production.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        $catalog = app(TagudinUndergraduateCatalog::class);
        $programsByCode = array_column($catalog->programs(), null, 'code');
        $programs = array_map(
            static fn (string $code): array => $programsByCode[$code],
            ['BSIT', 'BSEd', 'BEEd', 'BPEd', 'BSBA', 'BSEntrep', 'BSMath', 'BA-EL', 'BA-Psych', 'BASS', 'BPA'],
        );

        if (AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id) !== null) {
            return;
        }

        // Synthetic, manually authored five-tier fixture values for the 11-program benchmark set.
        $rawProfiles = [
            'BSIT' => [
                1 => ['strand' => 'STEM', 'gwa' => 91.0, 'gen' => 86, 'verb' => 78, 'num' => 88, 'spat' => 84, 'perc' => 82, 'man' => 76, 'math' => 92, 'sci' => 88, 'eng' => 84, 'riasec' => ['i' => 1, 'r' => 1], 'target' => 1],
                2 => ['strand' => 'TVL-ICT', 'gwa' => 86.0, 'gen' => 80, 'verb' => 74, 'num' => 82, 'spat' => 80, 'perc' => 76, 'man' => 74, 'math' => 86, 'sci' => 82, 'eng' => 80, 'riasec' => ['i' => 1, 'r' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 83.0, 'gen' => 73, 'verb' => 70, 'num' => 76, 'spat' => 72, 'perc' => 70, 'man' => 68, 'math' => 80, 'sci' => 78, 'eng' => 78, 'riasec' => ['i' => 1], 'target' => 1],
                4 => ['strand' => 'HUMSS', 'gwa' => 88.0, 'gen' => 82, 'verb' => 85, 'num' => 82, 'spat' => 76, 'perc' => 74, 'man' => 72, 'math' => 84, 'sci' => 80, 'eng' => 88, 'riasec' => ['i' => 1, 'a' => 1], 'target' => 1],
                5 => ['strand' => 'GAS', 'gwa' => 78.0, 'gen' => 60, 'verb' => 62, 'num' => 65, 'spat' => 60, 'perc' => 62, 'man' => 58, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['s' => 1, 'a' => 1], 'target' => 0],
            ],
            'BSEd' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 92.0, 'gen' => 85, 'verb' => 90, 'num' => 82, 'spat' => 75, 'perc' => 80, 'man' => 75, 'math' => 88, 'sci' => 88, 'eng' => 92, 'riasec' => ['s' => 1, 'a' => 1], 'target' => 1],
                2 => ['strand' => 'HUMSS', 'gwa' => 87.0, 'gen' => 80, 'verb' => 85, 'num' => 75, 'spat' => 70, 'perc' => 75, 'man' => 72, 'math' => 82, 'sci' => 82, 'eng' => 88, 'riasec' => ['s' => 1, 'a' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 84.0, 'gen' => 75, 'verb' => 78, 'num' => 70, 'spat' => 68, 'perc' => 70, 'man' => 68, 'math' => 80, 'sci' => 80, 'eng' => 82, 'riasec' => ['s' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 88.0, 'gen' => 82, 'verb' => 82, 'num' => 85, 'spat' => 74, 'perc' => 74, 'man' => 70, 'math' => 88, 'sci' => 86, 'eng' => 84, 'riasec' => ['s' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-ICT', 'gwa' => 78.0, 'gen' => 62, 'verb' => 65, 'num' => 60, 'spat' => 62, 'perc' => 60, 'man' => 58, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1, 'c' => 1], 'target' => 0],
            ],
            'BEEd' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 91.0, 'gen' => 82, 'verb' => 88, 'num' => 76, 'spat' => 78, 'perc' => 85, 'man' => 82, 'math' => 85, 'sci' => 85, 'eng' => 90, 'riasec' => ['s' => 1, 'a' => 1], 'target' => 1],
                2 => ['strand' => 'GAS', 'gwa' => 86.0, 'gen' => 78, 'verb' => 82, 'num' => 70, 'spat' => 72, 'perc' => 80, 'man' => 78, 'math' => 80, 'sci' => 80, 'eng' => 85, 'riasec' => ['s' => 1, 'a' => 1], 'target' => 1],
                3 => ['strand' => 'HUMSS', 'gwa' => 83.5, 'gen' => 72, 'verb' => 75, 'num' => 68, 'spat' => 68, 'perc' => 74, 'man' => 72, 'math' => 78, 'sci' => 78, 'eng' => 80, 'riasec' => ['s' => 1], 'target' => 1],
                4 => ['strand' => 'TVL-HE', 'gwa' => 87.0, 'gen' => 76, 'verb' => 78, 'num' => 70, 'spat' => 72, 'perc' => 82, 'man' => 84, 'math' => 80, 'sci' => 78, 'eng' => 82, 'riasec' => ['s' => 1, 'r' => 1], 'target' => 1],
                5 => ['strand' => 'STEM', 'gwa' => 77.0, 'gen' => 60, 'verb' => 62, 'num' => 62, 'spat' => 58, 'perc' => 60, 'man' => 58, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['c' => 1], 'target' => 0],
            ],
            'BPEd' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 89.0, 'gen' => 80, 'verb' => 80, 'num' => 72, 'spat' => 85, 'perc' => 88, 'man' => 92, 'math' => 82, 'sci' => 84, 'eng' => 84, 'riasec' => ['r' => 1, 's' => 1], 'target' => 1],
                2 => ['strand' => 'GAS', 'gwa' => 85.0, 'gen' => 75, 'verb' => 75, 'num' => 68, 'spat' => 80, 'perc' => 82, 'man' => 86, 'math' => 78, 'sci' => 80, 'eng' => 80, 'riasec' => ['r' => 1, 's' => 1], 'target' => 1],
                3 => ['strand' => 'TVL-HE', 'gwa' => 82.0, 'gen' => 70, 'verb' => 70, 'num' => 65, 'spat' => 75, 'perc' => 76, 'man' => 80, 'math' => 76, 'sci' => 76, 'eng' => 76, 'riasec' => ['r' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 86.0, 'gen' => 78, 'verb' => 76, 'num' => 75, 'spat' => 82, 'perc' => 80, 'man' => 84, 'math' => 82, 'sci' => 84, 'eng' => 80, 'riasec' => ['r' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'ABM', 'gwa' => 76.0, 'gen' => 58, 'verb' => 60, 'num' => 58, 'spat' => 60, 'perc' => 62, 'man' => 60, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['c' => 1], 'target' => 0],
            ],
            'BSBA' => [
                1 => ['strand' => 'ABM', 'gwa' => 92.0, 'gen' => 86, 'verb' => 84, 'num' => 88, 'spat' => 76, 'perc' => 82, 'man' => 74, 'math' => 90, 'sci' => 84, 'eng' => 88, 'riasec' => ['e' => 1, 'c' => 1], 'target' => 1],
                2 => ['strand' => 'ABM', 'gwa' => 87.0, 'gen' => 80, 'verb' => 78, 'num' => 82, 'spat' => 72, 'perc' => 78, 'man' => 70, 'math' => 85, 'sci' => 80, 'eng' => 84, 'riasec' => ['e' => 1, 'c' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 83.0, 'gen' => 74, 'verb' => 72, 'num' => 75, 'spat' => 68, 'perc' => 72, 'man' => 66, 'math' => 80, 'sci' => 78, 'eng' => 80, 'riasec' => ['e' => 1], 'target' => 1],
                4 => ['strand' => 'HUMSS', 'gwa' => 88.0, 'gen' => 82, 'verb' => 85, 'num' => 78, 'spat' => 72, 'perc' => 76, 'man' => 70, 'math' => 82, 'sci' => 80, 'eng' => 88, 'riasec' => ['e' => 1, 's' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-HE', 'gwa' => 78.0, 'gen' => 62, 'verb' => 64, 'num' => 62, 'spat' => 60, 'perc' => 60, 'man' => 62, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1], 'target' => 0],
            ],
            'BSEntrep' => [
                1 => ['strand' => 'ABM', 'gwa' => 91.0, 'gen' => 85, 'verb' => 82, 'num' => 84, 'spat' => 78, 'perc' => 80, 'man' => 78, 'math' => 88, 'sci' => 82, 'eng' => 86, 'riasec' => ['e' => 1, 'r' => 1], 'target' => 1],
                2 => ['strand' => 'TVL-HE', 'gwa' => 86.0, 'gen' => 78, 'verb' => 76, 'num' => 78, 'spat' => 74, 'perc' => 76, 'man' => 75, 'math' => 82, 'sci' => 78, 'eng' => 82, 'riasec' => ['e' => 1, 'r' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 82.5, 'gen' => 72, 'verb' => 70, 'num' => 72, 'spat' => 68, 'perc' => 70, 'man' => 70, 'math' => 78, 'sci' => 76, 'eng' => 78, 'riasec' => ['e' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 88.0, 'gen' => 82, 'verb' => 78, 'num' => 82, 'spat' => 80, 'perc' => 78, 'man' => 76, 'math' => 85, 'sci' => 84, 'eng' => 82, 'riasec' => ['e' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'HUMSS', 'gwa' => 77.0, 'gen' => 60, 'verb' => 64, 'num' => 60, 'spat' => 58, 'perc' => 60, 'man' => 58, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['a' => 1], 'target' => 0],
            ],
            'BSMath' => [
                1 => ['strand' => 'STEM', 'gwa' => 93.0, 'gen' => 90, 'verb' => 80, 'num' => 95, 'spat' => 88, 'perc' => 85, 'man' => 75, 'math' => 96, 'sci' => 90, 'eng' => 86, 'riasec' => ['i' => 1, 'c' => 1], 'target' => 1],
                2 => ['strand' => 'STEM', 'gwa' => 88.0, 'gen' => 84, 'verb' => 75, 'num' => 88, 'spat' => 82, 'perc' => 80, 'man' => 72, 'math' => 90, 'sci' => 85, 'eng' => 82, 'riasec' => ['i' => 1, 'c' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 84.0, 'gen' => 76, 'verb' => 70, 'num' => 82, 'spat' => 75, 'perc' => 74, 'man' => 68, 'math' => 84, 'sci' => 80, 'eng' => 78, 'riasec' => ['i' => 1], 'target' => 1],
                4 => ['strand' => 'ABM', 'gwa' => 89.0, 'gen' => 82, 'verb' => 78, 'num' => 88, 'spat' => 76, 'perc' => 82, 'man' => 70, 'math' => 90, 'sci' => 82, 'eng' => 84, 'riasec' => ['i' => 1, 'c' => 1], 'target' => 1],
                5 => ['strand' => 'HUMSS', 'gwa' => 78.0, 'gen' => 62, 'verb' => 70, 'num' => 64, 'spat' => 60, 'perc' => 62, 'man' => 58, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['s' => 1], 'target' => 0],
            ],
            'BA-EL' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 92.0, 'gen' => 86, 'verb' => 95, 'num' => 70, 'spat' => 72, 'perc' => 80, 'man' => 70, 'math' => 82, 'sci' => 82, 'eng' => 96, 'riasec' => ['a' => 1, 's' => 1], 'target' => 1],
                2 => ['strand' => 'HUMSS', 'gwa' => 87.0, 'gen' => 80, 'verb' => 90, 'num' => 65, 'spat' => 68, 'perc' => 75, 'man' => 68, 'math' => 78, 'sci' => 78, 'eng' => 92, 'riasec' => ['a' => 1, 's' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 83.5, 'gen' => 74, 'verb' => 82, 'num' => 62, 'spat' => 65, 'perc' => 70, 'man' => 66, 'math' => 76, 'sci' => 76, 'eng' => 85, 'riasec' => ['a' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 89.0, 'gen' => 84, 'verb' => 86, 'num' => 80, 'spat' => 75, 'perc' => 76, 'man' => 72, 'math' => 84, 'sci' => 85, 'eng' => 90, 'riasec' => ['a' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-ICT', 'gwa' => 77.0, 'gen' => 60, 'verb' => 65, 'num' => 62, 'spat' => 60, 'perc' => 60, 'man' => 62, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1], 'target' => 0],
            ],
            'BA-Psych' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 92.0, 'gen' => 88, 'verb' => 90, 'num' => 78, 'spat' => 76, 'perc' => 84, 'man' => 78, 'math' => 86, 'sci' => 90, 'eng' => 92, 'riasec' => ['s' => 1, 'i' => 1], 'target' => 1],
                2 => ['strand' => 'HUMSS', 'gwa' => 87.0, 'gen' => 82, 'verb' => 85, 'num' => 72, 'spat' => 72, 'perc' => 80, 'man' => 74, 'math' => 82, 'sci' => 84, 'eng' => 88, 'riasec' => ['s' => 1, 'i' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 84.0, 'gen' => 75, 'verb' => 80, 'num' => 68, 'spat' => 68, 'perc' => 74, 'man' => 70, 'math' => 78, 'sci' => 80, 'eng' => 82, 'riasec' => ['s' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 89.0, 'gen' => 85, 'verb' => 86, 'num' => 82, 'spat' => 76, 'perc' => 82, 'man' => 78, 'math' => 88, 'sci' => 90, 'eng' => 86, 'riasec' => ['s' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-Agri', 'gwa' => 78.0, 'gen' => 62, 'verb' => 66, 'num' => 60, 'spat' => 60, 'perc' => 62, 'man' => 60, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1, 'c' => 1], 'target' => 0],
            ],
            'BASS' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 91.0, 'gen' => 85, 'verb' => 92, 'num' => 72, 'spat' => 74, 'perc' => 80, 'man' => 72, 'math' => 84, 'sci' => 84, 'eng' => 92, 'riasec' => ['s' => 1, 'e' => 1], 'target' => 1],
                2 => ['strand' => 'HUMSS', 'gwa' => 86.0, 'gen' => 80, 'verb' => 86, 'num' => 68, 'spat' => 70, 'perc' => 76, 'man' => 70, 'math' => 80, 'sci' => 80, 'eng' => 88, 'riasec' => ['s' => 1, 'e' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 83.0, 'gen' => 74, 'verb' => 80, 'num' => 64, 'spat' => 66, 'perc' => 72, 'man' => 66, 'math' => 76, 'sci' => 78, 'eng' => 82, 'riasec' => ['s' => 1], 'target' => 1],
                4 => ['strand' => 'ABM', 'gwa' => 88.0, 'gen' => 82, 'verb' => 84, 'num' => 78, 'spat' => 72, 'perc' => 76, 'man' => 70, 'math' => 82, 'sci' => 80, 'eng' => 86, 'riasec' => ['s' => 1, 'e' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-HE', 'gwa' => 77.0, 'gen' => 60, 'verb' => 64, 'num' => 60, 'spat' => 58, 'perc' => 60, 'man' => 62, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1], 'target' => 0],
            ],
            'BPA' => [
                1 => ['strand' => 'HUMSS', 'gwa' => 91.0, 'gen' => 86, 'verb' => 90, 'num' => 78, 'spat' => 74, 'perc' => 82, 'man' => 74, 'math' => 86, 'sci' => 84, 'eng' => 90, 'riasec' => ['e' => 1, 's' => 1], 'target' => 1],
                2 => ['strand' => 'ABM', 'gwa' => 86.0, 'gen' => 80, 'verb' => 84, 'num' => 75, 'spat' => 70, 'perc' => 78, 'man' => 70, 'math' => 82, 'sci' => 80, 'eng' => 86, 'riasec' => ['e' => 1, 's' => 1], 'target' => 1],
                3 => ['strand' => 'GAS', 'gwa' => 83.0, 'gen' => 74, 'verb' => 78, 'num' => 70, 'spat' => 66, 'perc' => 72, 'man' => 66, 'math' => 78, 'sci' => 78, 'eng' => 80, 'riasec' => ['e' => 1], 'target' => 1],
                4 => ['strand' => 'STEM', 'gwa' => 88.0, 'gen' => 82, 'verb' => 82, 'num' => 82, 'spat' => 76, 'perc' => 78, 'man' => 72, 'math' => 85, 'sci' => 86, 'eng' => 84, 'riasec' => ['e' => 1, 'i' => 1], 'target' => 1],
                5 => ['strand' => 'TVL-HE', 'gwa' => 77.0, 'gen' => 60, 'verb' => 64, 'num' => 62, 'spat' => 60, 'perc' => 60, 'man' => 62, 'math' => 75, 'sci' => 75, 'eng' => 75, 'riasec' => ['r' => 1], 'target' => 0],
            ],
        ];

        $tierLabels = [
            1 => 'Honors High',
            2 => 'Solid Regular',
            3 => 'Minimum Cutoff',
            4 => 'Cross-Strand',
            5 => 'Below Cutoff',
        ];

        $exemplars = [];
        foreach ($programs as $prog) {
            $code = $prog['code'];
            $name = $prog['name'];
            $tiers = $rawProfiles[$code] ?? [];
            for ($tier = 1; $tier <= 5; $tier++) {
                $p = $tiers[$tier] ?? null;
                if ($p === null) {
                    continue;
                }
                $values = [
                    'gwa' => (float) $p['gwa'],
                    'LOGIC' => (float) $p['gen'],
                    'PROFILE_VERBAL' => (float) $p['verb'],
                    'PROFILE_QUANT' => (float) $p['num'],
                    'SPATIAL' => (float) $p['spat'],
                    'MEMORY' => (float) $p['perc'],
                    'LANGUAGE' => (float) $p['man'],
                ];
                $exemplars[] = [
                    'program_code' => $code,
                    'program_name' => $name,
                    'tier_level' => $tier,
                    'tier_label' => $tierLabels[$tier],
                    'target_class' => (int) $p['target'],
                    'values' => $values,
                ];
            }
        }

        $context = BenchmarkExecutionContext::trustedSeed($institution, 'seed');
        $set = app(ImportAdmissionBenchmarkSet::class)->handle($institution, $cycle, $context, [
            'name' => 'Synthetic Tagudin Undergraduate Benchmark Fixture (v2)',
            'change_reason' => 'Manually authored synthetic development profiles; not institutionally sourced or validated.',
            'programs' => $programs,
            'synthesized_exemplars' => $exemplars,
        ], 'synthetic-tagudin-undergraduate-benchmark-fixture-v2');

        app(ActivateAdmissionBenchmarkSet::class)->handle(
            $cycle,
            $set,
            $context,
            null,
            (string) $set->content_sha256,
            'synthetic-tagudin-undergraduate-benchmark-fixture-v2-activation',
        );

    }
}
