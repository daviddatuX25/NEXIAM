<?php

namespace App\Console\Commands\Demo;

use App\Testing\Actions\GenerateDemoPipelineApplicants;
use Illuminate\Console\Command;

class SeedPipelineCommand extends Command
{
    protected $signature = 'demo:seed-pipeline
        {count? : Number of applicants to autogenerate (e.g. 10)}
        {--count= : Number of applicants to autogenerate}
        {--status=pending : Initial attempt status (pending or accepted)}
        {--scenario= : Fixed use-case scenario, not a global cohort reset}
        {--operator= : Existing authorized operator user ID}
        {--purpose=demo : demo or research}
        {--set= : Owned set UUID for replace/retire}
        {--booklet= : Eligible institutional booklet version ID for prepare/replace}
        {--action=prepare : prepare, replace or retire}
        {--confirm= : Exact previously previewed operation UUID}';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['demo:pipeline', 'demo:generate-applicants'];

    protected $description = 'Autogenerate applicants for the demo admission pipeline with readable names';

    public function handle(GenerateDemoPipelineApplicants $generator): int
    {
        // 1. Legacy scenario / confirm workflow:
        if ($this->option('scenario') !== null || $this->option('confirm') !== null) {
            return StageCommand::executeOperation($this, [
                'purpose' => $this->option('purpose'),
                'scenario' => $this->option('scenario'),
                'action' => $this->option('action'),
                'set_id' => $this->option('set'),
                'booklet_version_id' => $this->option('booklet'),
            ]);
        }

        $countInput = $this->argument('count') ?? $this->option('count');

        // 2. Bare invocation (e.g. PipelineCohortSeederTest or bare CLI help):
        if ($countInput === null) {
            $this->info('NEXIAM Demo Pipeline · Application Generator');
            $this->line('Usage: php artisan demo:seed-pipeline <count> [--status=pending|accepted]');
            $this->line('Example: php artisan demo:seed-pipeline 10');
            $this->line('Or open /demo in your browser to choose how many applicants to autogenerate.');

            return self::SUCCESS;
        }

        $count = (int) $countInput;
        if ($count <= 0) {
            $this->error('The count must be a positive integer (e.g. 10).');

            return self::FAILURE;
        }

        $status = strtolower((string) ($this->option('status') ?: 'pending'));
        if (! in_array($status, ['pending', 'accepted'], true)) {
            $this->error('The status must be either "pending" or "accepted".');

            return self::FAILURE;
        }

        $this->info("Generating {$count} applicant(s) with readable names for the admission pipeline…");

        $result = $generator->generate($count, $status);

        $this->table(
            ['Reference', 'Full Name', 'Programs', 'Strand', 'School', 'Access Key', 'Status'],
            array_map(fn (array $app): array => [
                $app['reference_code'],
                $app['full_name'],
                implode(', ', $app['programs']),
                $app['strand'],
                $app['school'],
                $app['access_key'],
                $app['status'],
            ], $result['applicants'])
        );

        $this->info("✓ Successfully generated {$result['count']} applicant(s) in cycle {$result['cycle']['code']}.");
        $this->line('Inspect applications at /admissions or sign in as applicant with the access keys above.');

        return self::SUCCESS;
    }
}
