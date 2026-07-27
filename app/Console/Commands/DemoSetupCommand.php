<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ConsultationSummary;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DemoSetupCommand extends Command
{
    protected $signature = 'demo:setup
                            {--fresh : Drop and re-run all migrations before seeding (default: true)}
                            {--no-fresh : Skip migration refresh — seed only}';

    protected $description = 'Set up a fresh defense demo database (migrate:fresh + seed + DefenseDemoSeeder)';

    public function handle(): int
    {
        if (! config('demo.enabled', false)) {
            $this->error('DEMO is not enabled. Add DEMO=true to your .env file and try again.');
            $this->line('');
            $this->line('  Required .env additions:');
            $this->line('  DEMO=true');
            $this->line('  DEMO_THROTTLE_DECAY_SECONDS=15   # optional — for live rate-limit demo');
            $this->line('  DEMO_THROTTLE_ATTEMPTS=3          # optional');

            return self::FAILURE;
        }

        if (app()->isProduction()) {
            $this->error('demo:setup cannot run in production.');

            return self::FAILURE;
        }

        $fresh = ! $this->option('no-fresh');

        $this->info('SecureCAT — Defense Demo Setup');
        $this->line('─────────────────────────────────────────');

        // ── Step 1: migrate:fresh (optional) ─────────────────────────────────
        if ($fresh) {
            $this->line('  <comment>→</comment> Running migrate:fresh...');
            Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        }

        // ── Step 2: Foundation seeders ────────────────────────────────────────
        $this->line('  <comment>→</comment> Seeding foundation data (roles, courses, aptitude areas, templates)...');
        Artisan::call('db:seed', [
            '--class' => 'DatabaseSeeder',
            '--force' => true,
        ], $this->output);

        // ── Step 3: Summary ───────────────────────────────────────────────────
        $this->line('');
        $this->info('Done! Database is demo-ready.');
        $this->line('');
        $this->printSummary();
        $this->printSessionIds();
        $this->printThrottleInfo();
        $this->printCredentials();

        return self::SUCCESS;
    }

    private function printSummary(): void
    {
        $counts = [
            'Applications' => Application::count(),
            'Applicants' => Applicant::count(),
            'Exam sessions' => ExamSession::count(),
            'Rooms' => Room::count(),
            'Consultations' => ConsultationSummary::count(),
            'Staff users' => User::count(),
        ];

        $this->line('  <info>Database counts:</info>');
        foreach ($counts as $label => $count) {
            $this->line(sprintf('    %-20s %d', $label, $count));
        }
        $this->line('');
    }

    private function printThrottleInfo(): void
    {
        $seconds = config('demo.throttle_decay_seconds');
        $attempts = config('demo.throttle_attempts') ?? config('auth.login_throttle_attempts', 5);

        if ($seconds !== null) {
            $this->line(sprintf(
                '  <comment>Rate limiter (demo):</comment> %d attempts / %ds window',
                $attempts,
                $seconds
            ));
            $this->line('    To trip it: fail login '.$attempts.' times → wait '.$seconds.'s → try again.');
        } else {
            $decay = config('auth.login_throttle_decay_minutes', 15);
            $this->line("  <comment>Rate limiter (normal):</comment> {$attempts} attempts / {$decay}min window");
            $this->line('    Tip: add DEMO_THROTTLE_DECAY_SECONDS=15 to .env for a quick live demo.');
        }
        $this->line('');
    }

    private function printCredentials(): void
    {
        $this->line('  <info>Staff accounts (password: password)</info>');
        $this->table(
            ['Role', 'Email'],
            [
                ['super_admin',       'admin@securecat.local'],
                ['registrar_administrator', 'josefina@securecat.local'],
                ['staff',             'maria@securecat.local'],
                ['proctor',           'eduardo@securecat.local'],
                ['test_administrator',      'analiza@securecat.local'],
            ]
        );

        $this->line('  <info>Applicant portal accounts (password: password)</info>');
        $this->table(
            ['Name', 'Email', 'Session'],
            [
                ['Juan Carlo Agustin', 'juan.agustin@ispsc-demo.local',     'A — released'],
                ['Maricel Dacumos',    'maricel.dacumos@ispsc-demo.local',   'A — released'],
                ['Reynaldo Soriano',   'reynaldo.soriano@ispsc-demo.local',  'A — released'],
                ['Rowena Ballesteros', 'rowena.ballesteros@ispsc-demo.local', 'B — pending'],
                ['Danilo Espiritu Jr.', 'danilo.espiritu@ispsc-demo.local',   'B — pending'],
                ['Lorena Tamayo',      'lorena.tamayo@ispsc-demo.local',     'C — today (present)'],
                ['Roberto Libed',      'roberto.libed@ispsc-demo.local',     'C — today (pending)'],
                ['Maribel Pagulayan',  'maribel.pagulayan@ispsc-demo.local', 'C — today (pending)'],
                ['Arturo Madriaga',    'arturo.madriaga@ispsc-demo.local',   'C — today (pending)'],
            ]
        );

        $this->line('  See DEMO.md for the full step-by-step defense walkthrough.');
        $this->line('');
    }

    private function printSessionIds(): void
    {
        $this->line('  <info>Session IDs (use these in URLs):</info>');

        ExamSession::with('room')
            ->orderBy('date')
            ->get()
            ->each(function (ExamSession $session) {
                $this->line(sprintf(
                    '    ID %-4d | %-12s | %-10s | %s / %s',
                    $session->id,
                    $session->date,
                    $session->status,
                    $session->room->building ?? '—',
                    $session->room->name ?? '—'
                ));
            });

        $this->line('');
        $this->line('  <info>Mailpit (local mail):</info>  http://localhost:8025');
        $this->line('');
    }
}
