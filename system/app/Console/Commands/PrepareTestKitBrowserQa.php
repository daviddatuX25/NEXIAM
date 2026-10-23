<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\TestKitBrowserQaSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('test-kit:browser-qa:prepare {--setup-only : Prepare an additive isolated Setup-only Kit draft scenario.}')]
#[Description('Prepare guarded synthetic Test Kit browser QA data without fabricating verification or qualification.')]
final class PrepareTestKitBrowserQa extends Command
{
    public function handle(TestKitBrowserQaSeeder $seeder): int
    {
        try {
            $prepared = $seeder->run($this->option('setup-only'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('setup-only')) {
            $this->info('Additive synthetic Test Kit Setup-only browser scenario is ready.');
            $this->line('Isolated database: '.$prepared['database']);
            $this->line('Staff login: '.$prepared['staff_email']);
            $this->line('Staff password: '.$prepared['staff_password']);
            $this->line('Expected state: '.$prepared['expected_state']);
            $this->line('Kit URL: '.$prepared['kit_url']);
            $this->line('Recreate: php artisan test-kit:browser-qa:prepare --setup-only');

            return self::SUCCESS;
        }

        $this->info('Fresh synthetic Test Kit browser QA preparation reached mapping-complete.');
        $this->line('Isolated database: '.$prepared['database']);
        $this->line('Institution id: '.$prepared['institution_id'].'; cycle id: '.$prepared['cycle_id']);
        $this->line('Synthetic family id: '.$prepared['kit_id'].'; sheet family id: '.$prepared['template_id']);
        $this->line('Synthetic profile version id: '.$prepared['profile_version_id'].'; authoring draft id: '.$prepared['draft_id']);
        $this->line('Draft stage: '.$prepared['draft_stage'].'; digital verification: '.$prepared['verification_status'].'.');
        $this->warn('No verification pass, compile, Test Kit candidate version, acceptance receipt, physical stamp, or activation was created. Continue with the genuine staff verification flow and real omr-verification worker.');

        return self::SUCCESS;
    }
}
