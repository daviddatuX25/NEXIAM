<?php

declare(strict_types=1);

use App\Ai\Contracts\AiGateway;
use App\Console\Commands\Ai\EvaluateXiamScenariosCommand;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use Database\Seeders\XiamEvalSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

it('blocks live evaluation without explicit opt-in before actor, turn, or provider access', function (): void {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    $runsBefore = XiamRun::query()->count();
    $proposalsBefore = XiamProposal::query()->count();

    config([
        'app.env' => 'local',
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => 'nexiam',
        'xiam.eval.enabled' => false,
        'xiam.eval.database' => 'nexiam',
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $gatewayResolved = false;
    app()->bind(AiGateway::class, function () use (&$gatewayResolved): AiGateway {
        $gatewayResolved = true;

        return Mockery::mock(AiGateway::class);
    });

    $firstExitCode = Artisan::call('xiam:eval', ['--live' => true, '--json' => true, '--actor' => 'missing@nexiam.test']);
    $firstReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($firstExitCode)->toBe(1)
        ->and($firstReport)->toMatchArray([
            'command' => 'xiam:eval',
            'mode' => 'not_run',
            'blocked' => true,
            'remote_provider' => false,
            'error' => 'Live evaluation requires explicit XIAM_EVAL_ENABLED=true.',
        ]);

    config(['database.default' => 'sqlite']);
    expect($gatewayResolved)->toBeFalse()
        ->and($queries)->toBe([])
        ->and(XiamRun::query()->count())->toBe($runsBefore)
        ->and(XiamProposal::query()->count())->toBe($proposalsBefore);

    config([
        'xiam.eval.enabled' => true,
        'xiam.eval.database' => ':memory:',
    ]);

    $secondExitCode = Artisan::call('xiam:eval', ['--live' => true, '--json' => true, '--actor' => 'missing@nexiam.test']);
    $secondReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($secondExitCode)->toBe(1)
        ->and($secondReport)->toMatchArray([
            'command' => 'xiam:eval',
            'mode' => 'not_run',
            'blocked' => true,
            'remote_provider' => false,
            'error' => 'The connected database is not an explicitly approved isolated Xiam evaluation database.',
        ]);

    expect($gatewayResolved)->toBeFalse()
        ->and(XiamRun::query()->count())->toBe($runsBefore)
        ->and(XiamProposal::query()->count())->toBe($proposalsBefore);
});
it('rejects an unallowlisted PostgreSQL host before opening the database or resolving a provider', function (): void {
    $originalAppEnvironment = config('app.env');
    $originalDatabaseDefault = config('database.default');
    $originalPgsqlConnection = config('database.connections.pgsql');
    $originalEvalConfig = [
        'enabled' => config('xiam.eval.enabled'),
        'database' => config('xiam.eval.database'),
        'database_hosts' => config('xiam.eval.database_hosts'),
    ];

    try {
        config([
            'app.env' => 'local',
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.host' => 'production-db.example.test',
            'database.connections.pgsql.database' => 'nexiam_xiam_eval',
            'xiam.eval.enabled' => true,
            'xiam.eval.database' => 'nexiam_xiam_eval',
            'xiam.eval.database_hosts' => ['xiam-eval-postgres'],
        ]);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $gatewayResolved = false;
        app()->bind(AiGateway::class, function () use (&$gatewayResolved): AiGateway {
            $gatewayResolved = true;

            return Mockery::mock(AiGateway::class);
        });

        $exitCode = Artisan::call('xiam:eval', ['--live' => true, '--json' => true, '--actor' => 'missing@nexiam.test']);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($report)->toMatchArray([
                'command' => 'xiam:eval',
                'mode' => 'not_run',
                'blocked' => true,
                'remote_provider' => false,
                'error' => 'The connected database is not an explicitly approved isolated Xiam evaluation database.',
            ])
            ->and($gatewayResolved)->toBeFalse()
            ->and($queries)->toBe([]);
    } finally {
        config([
            'app.env' => $originalAppEnvironment,
            'database.default' => $originalDatabaseDefault,
            'database.connections.pgsql' => $originalPgsqlConnection,
            'xiam.eval.enabled' => $originalEvalConfig['enabled'],
            'xiam.eval.database' => $originalEvalConfig['database'],
            'xiam.eval.database_hosts' => $originalEvalConfig['database_hosts'],
        ]);
        DB::purge('pgsql');
    }

    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('refuses the evaluation seeder on the ordinary database name before seeder queries', function (): void {
    config([
        'app.env' => 'local',
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'xiam.eval.enabled' => true,
        'xiam.eval.database' => 'nexiam',
    ]);

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(fn () => (new XiamEvalSeeder)->run())
        ->toThrow(RuntimeException::class, 'explicitly approved isolated evaluation database')
        ->and($queries)->toBe([]);
});
it('blocks direct evaluation seeding with eval disabled without modifying test data', function (): void {
    config(['xiam.eval.enabled' => false]);

    $rowCounts = static fn (): array => [
        'institutions' => DB::table('institutions')->count(),
        'users' => DB::table('users')->count(),
        'staff_profiles' => DB::table('staff_profiles')->count(),
    ];
    $before = $rowCounts();

    expect(fn () => (new XiamEvalSeeder)->run())
        ->toThrow(RuntimeException::class, 'Xiam evaluation seeding requires an explicitly approved isolated evaluation database.');

    expect($rowCounts())->toBe($before);
});
it('rejects switched or omitted unscored and completed session counts', function (): void {
    $command = app(EvaluateXiamScenariosCommand::class);
    $grader = new ReflectionMethod($command, 'answerGrounding');
    $case = [
        'critical' => true,
        'final_rubric_predicates' => ['grounded_numeric_claims_match_tool_evidence'],
        'required_answer_facts' => [
            [
                'tool_id' => 'assessment.unscored_examinees.query@1',
                'result_path' => 'unscored_examinees_count',
                'match_kind' => 'exact_count',
                'response_pattern' => '/\\b(\\d+)\\s+unscored examinees\\b/i',
            ],
            [
                'tool_id' => 'assessment.unscored_examinees.query@1',
                'result_path' => 'completed_sessions_count',
                'match_kind' => 'exact_count',
                'response_pattern' => '/\\b(\\d+)\\s+completed sessions\\b/i',
            ],
        ],
    ];
    $observations = [[
        'capability_id' => 'assessment.unscored_examinees.query@1',
        'ok' => true,
        'safe_data' => ['unscored_examinees_count' => 4, 'completed_sessions_count' => 2],
    ]];

    $switchedCounts = $grader->invoke($command, $case, 'There are 2 unscored examinees and 4 completed sessions.', $observations);
    $omittedCount = $grader->invoke($command, $case, 'There are 4 unscored examinees.', $observations);
    $matchingCounts = $grader->invoke($command, $case, 'There are 4 unscored examinees and 2 completed sessions.', $observations);

    expect($switchedCounts['status'])->toBe('mismatch')
        ->and($switchedCounts['blocking'])->toBeTrue()
        ->and($omittedCount['status'])->toBe('mismatch')
        ->and($omittedCount['blocking'])->toBeTrue()
        ->and($matchingCounts)->toMatchArray(['status' => 'pass', 'blocking' => false]);
});
