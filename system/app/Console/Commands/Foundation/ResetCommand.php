<?php

namespace App\Console\Commands\Foundation;

use Database\Seeders\ApplicantPortalDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ResetCommand extends Command
{
    protected $signature = 'foundation:reset {--force : Required safety latch for destructive reset}';

    protected $description = 'Reset only the greenfield database and private storage, then reseed synthetically';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to reset without --force.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'pgsql' && ! app()->environment('testing')) {
            $this->error('foundation:reset only targets the greenfield pgsql connection outside testing.');

            return self::FAILURE;
        }

        $legacyHints = [
            config('database.connections.pgsql.database'),
            config('database.connections.pgsql.host'),
        ];

        foreach ($legacyHints as $hint) {
            if (is_string($hint) && str_contains(strtolower($hint), 'legacy')) {
                $this->error('Refusing to reset a connection that looks legacy-scoped.');

                return self::FAILURE;
            }
        }

        $this->info('Refreshing greenfield schema...');
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->line(Artisan::output());

        // Applicant-portal demo (status-spine hub scenarios) is dev-reset-only:
        // it reserves quota, which would perturb quota-edge tests if it ran in
        // the default DatabaseSeeder seed.
        Artisan::call('db:seed', [
            '--class' => ApplicantPortalDemoSeeder::class,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $privateRoot = storage_path('app/private');
        File::ensureDirectoryExists($privateRoot);
        File::cleanDirectory($privateRoot);
        Storage::disk('local')->makeDirectory('foundation');

        $this->info('Greenfield database and private storage reset complete.');

        return self::SUCCESS;
    }
}
