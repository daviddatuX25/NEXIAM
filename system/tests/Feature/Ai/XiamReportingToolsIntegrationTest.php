<?php

declare(strict_types=1);

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolGateway;
use App\Authorization\CapabilityCatalog;
use App\Models\User;
use App\Reports\Queries\ApplicationsCensusDataQuery;
use Illuminate\Support\Str;

function reportingInvocationContext(User $actor, ?int $institutionId = null): InvocationContext
{
    return new InvocationContext(
        actorId: (string) $actor->getKey(),
        actorType: 'staff',
        institutionId: $institutionId ?? (int) $actor->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'reporting-tools-test',
        purpose: 'assistant_tool',
        correlationId: (string) Str::uuid(),
    );
}

test('registrar reporting tools return aggregate safe metrics to authorized registrar staff', function (): void {
    seedE2Foundation();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $context = reportingInvocationContext($registrar);
    $gateway = app(AssistantToolGateway::class);

    $census = $gateway->invoke($registrar, $context, 'reports.applications_census@1', []);
    expect($census->ok)->toBeTrue()
        ->and($census->data)->toHaveKey('total_applications')
        ->and($census->data)->toHaveKey('status_distribution')
        ->and($census->data)->toHaveKey('program_distribution')
        ->and($census->data)->toHaveKey('sex_distribution')
        ->and($census->data)->not->toHaveKey('applications');

    $feeders = $gateway->invoke($registrar, $context, 'reports.feeder_high_schools@1', []);
    expect($feeders->ok)->toBeTrue()
        ->and($feeders->data)->toHaveKey('total_feeders_count')
        ->and($feeders->data)->toHaveKey('top_schools')
        ->and($feeders->data)->toHaveKey('province_distribution');

    $logbook = $gateway->invoke($registrar, $context, 'reports.daily_admissions_logbook@1', []);
    expect($logbook->ok)->toBeTrue()
        ->and($logbook->data)->toHaveKey('target_date')
        ->and($logbook->data)->toHaveKey('total_daily_applicants')
        ->and($logbook->data)->toHaveKey('strand_distribution')
        ->and($logbook->data)->not->toHaveKey('applicants');

    $quota = $gateway->invoke($registrar, $context, 'reports.college_program_quota_summary@1', []);
    expect($quota->ok)->toBeTrue()
        ->and($quota->data)->toHaveKey('total_quota')
        ->and($quota->data)->toHaveKey('total_committed')
        ->and($quota->data)->toHaveKey('programs')
        ->and($quota->data)->toHaveKey('departments');
});

test('guidance reporting tools return aggregate safe metrics to authorized guidance counselor', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $context = reportingInvocationContext($guidance);
    $gateway = app(AssistantToolGateway::class);

    $scores = $gateway->invoke($guidance, $context, 'reports.score_distribution@1', []);
    expect($scores->ok)->toBeTrue()
        ->and($scores->data)->toHaveKey('total_examinees_scored')
        ->and($scores->data)->toHaveKey('subtests')
        ->and($scores->data)->not->toHaveKey('examinees');

    $attendance = $gateway->invoke($guidance, $context, 'reports.exam_attendance@1', []);
    expect($attendance->ok)->toBeTrue()
        ->and($attendance->data)->toHaveKey('total_sessions')
        ->and($attendance->data)->toHaveKey('total_registered')
        ->and($attendance->data)->toHaveKey('overall_attendance_rate_percent');

    $sessionAttendance = $gateway->invoke($guidance, $context, 'reports.exam_session_attendance@1', []);
    expect($sessionAttendance->ok)->toBeTrue()
        ->and($sessionAttendance->data)->toHaveKey('session_code')
        ->and($sessionAttendance->data)->toHaveKey('registered_count')
        ->and($sessionAttendance->data)->not->toHaveKey('examinees');
});

test('institutional reporting tools return safe facility and milestone metrics to executive administration', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->first()
        ?? User::permission(CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW)->firstOrFail();
    $context = reportingInvocationContext($admin);
    $gateway = app(AssistantToolGateway::class);

    $rooms = $gateway->invoke($admin, $context, 'reports.room_utilization@1', []);
    expect($rooms->ok)->toBeTrue()
        ->and($rooms->data)->toHaveKey('total_rooms')
        ->and($rooms->data)->toHaveKey('total_capacity')
        ->and($rooms->data)->toHaveKey('rooms');

    $staff = $gateway->invoke($admin, $context, 'reports.staff_accomplishment@1', []);
    expect($staff->ok)->toBeTrue()
        ->and($staff->data)->toHaveKey('total_applications_received')
        ->and($staff->data)->toHaveKey('milestones');
});

test('reporting tools strictly fence results by tenant institution', function (): void {
    seedE2Foundation();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $gateway = app(AssistantToolGateway::class);

    // 1. Invocation context mismatch across tenants is blocked by the gateway envelope
    $foreignContext = reportingInvocationContext($registrar, institutionId: 99999);
    $denied = $gateway->invoke($registrar, $foreignContext, 'reports.applications_census@1', []);
    expect($denied->ok)->toBeFalse();

    // 2. Query execution with a foreign cycle ID returns zero local records
    $scopedQuery = app(ApplicationsCensusDataQuery::class);
    $safeData = $scopedQuery->forXiamSafe((int) $registrar->actorScope()->institutionId, cycleId: 99999);
    expect($safeData['total_applications'])->toBe(0);
});
