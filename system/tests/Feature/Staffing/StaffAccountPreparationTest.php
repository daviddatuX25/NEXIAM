<?php

use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function preparationPayload(Program $program, string $operationId): array
{
    return [
        'operation_id' => $operationId,
        'mode' => 'create',
        'name' => 'Reviewed Program Head',
        'email' => null,
        'office' => 'program',
        'roles' => ['program_head'],
        'program_codes' => [$program->code],
        'demo_mode' => false,
    ];
}

it('rejects a preparation review when its active-program scope changes', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $department->institution_id,
        'department_id' => $department->id,
        'code' => 'PREP-'.Str::upper(Str::random(5)),
        'name' => 'Uncovered preparation program',
        'is_active' => true,
    ]);
    $operationId = (string) Str::uuid();
    $payload = preparationPayload($program, $operationId);

    $preview = $this->actingAs($hr)->postJson(route('staff.personnel.prepare.preview'), $payload)->assertOk();
    $program->update(['is_active' => false]);

    $this->actingAs($hr)
        ->post(route('staff.personnel.prepare.commit'), [...$payload, 'review_token' => $preview->json('review_token')])
        ->assertSessionHasErrors('review_token');

    expect(User::query()->where('name', 'Reviewed Program Head')->exists())->toBeFalse();
});

it('returns a safe receipt on an identical preparation retry without creating a duplicate', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $department->institution_id,
        'department_id' => $department->id,
        'code' => 'PREP-'.Str::upper(Str::random(5)),
        'name' => 'Uncovered preparation program',
        'is_active' => true,
    ]);
    $payload = preparationPayload($program, (string) Str::uuid());
    $preview = $this->actingAs($hr)->postJson(route('staff.personnel.prepare.preview'), $payload)->assertOk();
    $commit = [...$payload, 'review_token' => $preview->json('review_token')];

    $this->actingAs($hr)->post(route('staff.personnel.prepare.commit'), $commit)->assertRedirect(route('staff.personnel.index'));
    $this->actingAs($hr)->post(route('staff.personnel.prepare.commit'), $commit)->assertRedirect(route('staff.personnel.index'));

    expect(User::query()->where('name', 'Reviewed Program Head')->count())->toBe(1)
        ->and(User::query()->where('name', 'Reviewed Program Head')->value('email'))->toBeNull();
});
it('rejects reviewed preparation when program coverage appears before commit', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $department->institution_id,
        'department_id' => $department->id,
        'code' => 'PREP-'.Str::upper(Str::random(5)),
        'name' => 'Uncovered preparation program',
        'is_active' => true,
    ]);
    $payload = preparationPayload($program, (string) Str::uuid());
    $preview = $this->actingAs($hr)->postJson(route('staff.personnel.prepare.preview'), $payload)->assertOk();
    $coveredHead = app(CreateStaffAccount::class)->handle(
        $hr,
        'Concurrent Program Head',
        null,
        ['program_head'],
        'program',
        (int) $hr->actorScope()->institutionId,
        [$program->code],
    );

    $this->actingAs($hr)
        ->post(route('staff.personnel.prepare.commit'), [...$payload, 'review_token' => $preview->json('review_token')])
        ->assertSessionHasErrors();

    expect($coveredHead->fresh()->staffProfile->program_codes)->toContain($program->code)
        ->and(User::query()->where('name', 'Reviewed Program Head')->exists())->toBeFalse();
});
it('prepares an identified academic recipient for Dean assignment without claiming department ownership', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->create([
        'institution_id' => $hr->actorScope()->institutionId,
        'code' => 'DEAN-'.Str::upper(Str::random(5)),
        'name' => 'Unassigned Dean Test Department',
        'is_active' => true,
    ]);
    $payload = [
        'mode' => 'prepare_dean',
        'department_id' => $department->id,
        'operation_id' => (string) Str::uuid(),
        'name' => 'Prepared Department Dean',
        'email' => null,
        'office' => 'program',
    ];
    $preview = $this->actingAs($hr)->postJson(route('staff.personnel.prepare.preview'), $payload)->assertOk();

    $this->actingAs($hr)->post(route('staff.personnel.prepare.commit'), [
        ...$payload,
        'review_token' => $preview->json('review_token'),
    ])->assertRedirect(route('staff.personnel.index'))
        ->assertSessionHas('status', 'Recipient prepared for Dean assignment. The department remains uncovered until an authorized Admin Office Setup user assigns this person in Departments.');

    $prepared = User::query()->where('name', 'Prepared Department Dean')->with(['staffProfile', 'roles'])->firstOrFail();
    expect($prepared->hasRole('program_head'))->toBeTrue()
        ->and($prepared->staffProfile->office)->toBe('program')
        ->and($prepared->staffProfile->program_codes)->toBe([])
        ->and($department->fresh()->dean_user_id)->toBeNull();
});
it('commits an identified multi-recipient batch with independent row receipts', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $codes = ['BATCH-'.Str::upper(Str::random(5)), 'BATCH-'.Str::upper(Str::random(5))];
    foreach ($codes as $code) {
        Program::query()->create([
            'institution_id' => $department->institution_id,
            'department_id' => $department->id,
            'code' => $code,
            'name' => $code.' batch program',
            'is_active' => true,
        ]);
    }
    $recipients = [];
    foreach ($codes as $index => $code) {
        $recipients[] = [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Batch Program Head '.($index + 1),
            'email' => null,
            'office' => 'program',
            'roles' => ['program_head'],
            'program_codes' => [$code],
            'demo_mode' => false,
        ];
    }
    $preview = $this->actingAs($hr)->postJson(route('staff.personnel.prepare.preview'), ['recipients' => $recipients])->assertOk();
    $this->actingAs($hr)->post(route('staff.personnel.prepare.commit'), [
        'recipients' => $recipients,
        'review_token' => $preview->json('review_token'),
    ])->assertRedirect()->assertSessionHas('preparation_results', fn (array $results): bool => count($results) === 2 && collect($results)->every(fn (array $result) => $result['outcome'] === 'committed')
    );

    expect(User::query()->whereIn('name', ['Batch Program Head 1', 'Batch Program Head 2'])->count())->toBe(2)
        ->and(DB::table('staff_account_operation_receipts')->where('purpose', 'prepare')->count())->toBe(2);
});
