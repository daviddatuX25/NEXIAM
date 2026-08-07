<?php

use App\Foundation\Actions\CommitFoundationDemo;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Foundation\RunFoundationSideEffect;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

test('deterministic seed creates synthetic institution and cycle', function (): void {
    Artisan::call('db:seed', ['--force' => true]);

    expect(Institution::query()->where('code', 'SYN-INST-001')->exists())->toBeTrue()
        ->and(AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'foundation.seed_applied')->exists())->toBeTrue();
});

test('foundation demo commits idempotently and dispatches after commit', function (): void {
    Queue::fake();

    $first = app(CommitFoundationDemo::class)->handle('demo-key-1', ['marker' => 'a']);
    $second = app(CommitFoundationDemo::class)->handle('demo-key-1', ['marker' => 'b']);

    expect($first->id)->toBe($second->id)
        ->and(Operation::query()->count())->toBe(1)
        ->and($first->status)->toBe(Operation::STATUS_PENDING);

    Queue::assertPushed(RunFoundationSideEffect::class, 1);
});

test('foundation side effect job marks operation succeeded and writes private storage', function (): void {
    Queue::fake();

    $operation = app(CommitFoundationDemo::class)->handle('demo-key-2', ['marker' => 'storage']);

    $job = new RunFoundationSideEffect($operation->id);
    $job->handle(app(RecordAuditEvent::class));

    $operation->refresh();

    expect($operation->status)->toBe(Operation::STATUS_SUCCEEDED)
        ->and(Storage::disk('local')->exists('foundation/operations/'.$operation->id.'.json'))->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'foundation.operation_succeeded')->exists())->toBeTrue();
});

test('foundation side effect terminal failure is visible on the operation', function (): void {
    Queue::fake();

    $operation = app(CommitFoundationDemo::class)->handle('demo-key-fail', ['marker' => 'fail']);

    $job = new RunFoundationSideEffect($operation->id);
    $job->failed(new RuntimeException('synthetic worker failure'));

    $operation->refresh();

    expect($operation->status)->toBe(Operation::STATUS_FAILED)
        ->and($operation->last_error)->toBe('synthetic worker failure')
        ->and($operation->finished_at)->not->toBeNull();
});
