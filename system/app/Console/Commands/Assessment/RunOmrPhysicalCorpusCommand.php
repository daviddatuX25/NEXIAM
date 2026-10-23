<?php

namespace App\Console\Commands\Assessment;

use App\Assessment\Omr\Qualification\OmrPhysicalCorpusBenchmark;
use App\Assessment\Omr\Qualification\OmrPhysicalCorpusManifest;
use App\Assessment\Omr\Qualification\OmrPhysicalCorpusRunner;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('omr:qualification-corpus {run : Physical verification run ID} {manifest : Corpus manifest path} {--baseline= : Prior JSON report for zero-regression comparison} {--baseline-hash= : Pinned sha256 of the prior report} {--json : Emit machine-readable JSON}')]
#[Description('Run the pinned OMR strategy against a labeled real-photo qualification corpus')]
final class RunOmrPhysicalCorpusCommand extends Command
{
    public function handle(OmrPhysicalCorpusRunner $runner, OmrPhysicalCorpusBenchmark $benchmark): int
    {
        try {
            $manifest = OmrPhysicalCorpusManifest::fromPath((string) $this->argument('manifest'));
            $baselinePath = $this->option('baseline');
            if ($manifest->mode === 'production'
                && (! is_string($baselinePath) || trim($baselinePath) === '')) {
                throw new \InvalidArgumentException('qualification_baseline_required');
            }

            $baseline = null;
            if (is_string($baselinePath) && trim($baselinePath) !== '') {
                $resolvedBaseline = realpath($baselinePath);
                if ($resolvedBaseline === false || ! is_file($resolvedBaseline)) {
                    throw new \InvalidArgumentException('qualification_baseline_missing');
                }
                $baseline = json_decode((string) file_get_contents($resolvedBaseline), true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($baseline)) {
                    throw new \InvalidArgumentException('qualification_baseline_invalid');
                }
                $baselineHash = $this->option('baseline-hash');
                if (! is_string($baselineHash)
                    || preg_match('/^[a-f0-9]{64}$/', $baselineHash) !== 1
                    || ! hash_equals($baselineHash, (string) ($baseline['report_hash'] ?? ''))) {
                    throw new \InvalidArgumentException('qualification_baseline_hash_mismatch');
                }
            }

            $run = OmrTemplateVerificationRun::query()->findOrFail((int) $this->argument('run'));
            $report = $runner->run($run, $manifest);
            if ($baseline !== null) {
                $report = $benchmark->applyComparisonGate($baseline, $report);
            }
        } catch (Throwable $exception) {
            $error = [
                'contract' => 'nexiam.omr.qualification-result.v1',
                'gate' => ['passed' => false, 'status' => 'blocked', 'reason_codes' => [$exception->getMessage()]],
            ];
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($error, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Corpus {$report['corpus_id']}: {$report['gate']['status']}");
            $this->table(
                ['Capture', 'Class', 'Expected', 'Actual', 'Gate'],
                array_map(static fn (array $case): array => [
                    $case['id'],
                    $case['classification'],
                    $case['expected_outcome'],
                    $case['actual_outcome'],
                    $case['passed'] ? 'PASS' : 'FAIL',
                ], $report['cases']),
            );
            foreach ($report['gate']['reason_codes'] as $reason) {
                $this->components->warn((string) $reason);
            }
            $this->line("Report hash: {$report['report_hash']}");
        }

        return $report['gate']['passed'] === true ? self::SUCCESS : self::FAILURE;
    }
}
