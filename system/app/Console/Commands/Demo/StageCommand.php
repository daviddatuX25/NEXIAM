<?php

namespace App\Console\Commands\Demo;

use App\Models\User;
use App\Testing\Actions\ActivateDemoStage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StageCommand extends Command
{
    protected $signature = 'demo:stage {stage? : Fixed scenario or legacy stage name} {--operator= : Existing authorized operator user ID} {--purpose=demo : demo or research} {--set= : Owned set UUID for replace/retire} {--booklet= : Eligible institutional booklet version ID for prepare/replace} {--action=prepare : prepare, replace or retire} {--confirm= : Exact previously previewed operation UUID}';

    protected $description = 'Preview or exactly confirm safe scoped practice preparation; no automatic refresh';

    public const PHASE_CATALOG = [
        0 => ['key' => 'setup', 'scenario' => 'staff-accounts-delegation', 'title' => 'Read institutional prerequisites'],
        1 => ['key' => 'review', 'scenario' => 'application-review', 'title' => 'Application review'],
        2 => ['key' => 'reservation', 'scenario' => 'exam-scheduling-attendance', 'title' => 'Exam scheduling and attendance'],
        3 => ['key' => 'conduct', 'scenario' => 'exam-scheduling-attendance', 'title' => 'Exam scheduling and attendance'],
        4 => ['key' => 'consultation', 'scenario' => 'scoring-results', 'title' => 'Scoring and actual documents'],
        5 => ['key' => 'program', 'scenario' => 'consultation-referral', 'title' => 'Consultation starting cases'],
        6 => ['key' => 'enrollment', 'scenario' => 'enrollment-verification', 'title' => 'Enrollment prerequisite cases'],
    ];

    public function handle(): int
    {
        $stage = $this->argument('stage');
        if (($stage === null || in_array($stage, ['list', 'help'], true)) && $this->option('confirm') === null) {
            $this->table(['Stage', 'Scenario', 'Activity'], array_map(fn (array $phase): array => [$phase['key'], $phase['scenario'], $phase['title']], self::PHASE_CATALOG));
            $this->line('Select one scenario with --operator=<user ID>. First invocation previews only; use --confirm=<operation UUID> to confirm that exact operation. Staff use normal authentication, never guessed passwords.');

            return self::SUCCESS;
        }
        $scenario = $this->resolveScenario((string) $stage);
        if ($scenario === null && $this->option('confirm') === null) {
            $this->error('Unknown stage/scenario. Use the nonmutating menu or a fixed preparation scenario.');

            return self::FAILURE;
        }

        return self::executeOperation($this, [
            'purpose' => $this->option('purpose'), 'scenario' => $scenario,
            'action' => $this->option('action'), 'set_id' => $this->option('set'),
            'booklet_version_id' => $this->option('booklet'),
        ]);
    }

    /** @param array<string, mixed> $input */
    public static function executeOperation(Command $command, array $input): int
    {
        $operatorId = filter_var($command->option('operator'), FILTER_VALIDATE_INT);
        if ($operatorId === false || $operatorId < 1) {
            $command->error('An explicit existing --operator=<user ID> is required.');

            return self::FAILURE;
        }
        try {
            $operator = User::query()->findOrFail($operatorId);
            $operation = $command->option('confirm');
            $result = app(ActivateDemoStage::class)->handle($operator,
                array_filter($input, fn (mixed $value): bool => $value !== null && $value !== ''),
                is_string($operation) && $operation !== '' ? $operation : null);
            $command->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return ($result['can_confirm'] ?? true) ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException|HttpException|ModelNotFoundException|ValidationException $exception) {
            $command->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveScenario(string $stage): ?string
    {
        foreach (self::PHASE_CATALOG as $number => $phase) {
            if ($stage === (string) $number || $stage === $phase['key']) {
                return $phase['scenario'];
            }
        }
        if (in_array($stage, ['seating', 'grading'], true)) {
            return $stage === 'seating' ? 'exam-scheduling-attendance' : 'scoring-results';
        }

        return in_array($stage, ['application-review', 'exam-scheduling-attendance', 'scoring-results',
            'consultation-referral', 'case-coordination', 'enrollment-verification', 'staff-accounts-delegation',
            'account-technical-support', 'student', 'registrar', 'guidance', 'academic-leader'], true) ? $stage : null;
    }
}
