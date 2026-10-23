<?php

use App\Admission\Actions\OpenProgramFollowUp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\Program;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\ProgramHead\Queries\ProgramHeadDashboardQuery;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

it('shares unresolved review counts across program projections independently of list search', function (): void {
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'review-appearance@example.test');
    $program = Program::query()->where('code', $attempt->program_preference_1_code)->firstOrFail();
    $open = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, $program->code, 'Needs program review', 'pending-review-open');
    $open->update(['status' => ProgramFollowUp::STATUS_APPEARANCE_REQUESTED]);

    [$secondAttempt, , $secondGuidance] = buildMessagingReadyAttempt($this, 'review-resolved@example.test');
    $resolved = app(OpenProgramFollowUp::class)->handle($secondAttempt, $secondGuidance, $program->code, 'Resolved review', 'pending-review-resolved');
    $resolved->update(['status' => ProgramFollowUp::STATUS_CLEARED_BY_PH, 'resolved_at' => now()]);

    $lists = app(ProgramHeadCandidateListsQuery::class)->for($program, 'SYN-CYCLE-2026', 'no matching applicant');
    $dashboard = app(ProgramHeadDashboardQuery::class)->for($program, 'SYN-CYCLE-2026');

    expect($lists['pending_review_count'])->toBe(1)
        ->and($dashboard['metrics']['pending_review_count'])->toBe(1)
        ->and($lists['follow_ups'])->toBeEmpty();
});
