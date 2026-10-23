<?php

declare(strict_types=1);

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Context\XiamNavigationTargets;
use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Ai\XiamRunEvent;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;

test('navigation references remain fresh, actor scoped and bound to owned destinations', function (): void {
    seedE2Foundation();
    $this->seed(ApplicantOfficeInquiryDemoSeeder::class);

    $applicant = ApplicantOfficeInquiryDemoActor::query()
        ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)
        ->where('actor_type', ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT)
        ->firstOrFail()->user;
    $staff = ApplicantOfficeInquiryDemoActor::query()
        ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)
        ->where('actor_type', ApplicantOfficeInquiryDemoActor::TYPE_STAFF)
        ->firstOrFail()->user;
    $targets = app(XiamNavigationTargets::class);

    $applicantTargets = $targets->forActor($applicant, 'applicant');
    $applicantIds = array_column($applicantTargets, 'id');
    expect($applicantIds)->toContain('applicant.portal', 'applicant.office_inquiries')
        ->and(collect($applicantTargets)->every(fn (array $target): bool => str_starts_with($target['href'], '/')))->toBeTrue()
        ->and(collect($applicantTargets)->pluck('id')->every(fn (string $id): bool => ! str_starts_with($id, 'staff.')))->toBeTrue();

    $ownApplication = collect($applicantTargets)->firstWhere('id', 'applicant.application');
    expect($ownApplication)->not->toBeNull();
    $foreignAttemptId = (string) ((int) str($ownApplication['href'])->afterLast('/')->before('?')->toString() + 999999);
    $replayed = $targets->reproject($applicant, [[
        'id' => 'applicant.application',
        'label' => 'Forged label',
        'href' => '/applicant/applications/'.$foreignAttemptId,
        'attempt_id' => $foreignAttemptId,
    ]], 'applicant');
    expect($replayed)->toHaveCount(1)
        ->and($replayed[0]['label'])->toBe($ownApplication['label'])
        ->and($replayed[0]['href'])->toBe($ownApplication['href'])
        ->and($replayed[0]['href'])->not->toContain($foreignAttemptId);

    $staffTargets = $targets->forActor($staff, 'staff');
    expect(array_column($staffTargets, 'id'))->toContain('staff.staff.inquiries.index')
        ->and(collect($staffTargets)->pluck('id')->every(fn (string $id): bool => ! str_starts_with($id, 'applicant.')))->toBeTrue();

    $forged = $targets->projectResponse('Open [[navigate:staff.staff.inquiries.index]] and /admissions now.', $applicant, 'applicant');
    expect($forged['navigation'])->toBe([])
        ->and($forged['text'])->not->toContain('/admissions')
        ->and($forged['text'])->not->toContain('staff.inquiries');
    $events = [
        new XiamRunEvent(['run_id' => 'navigation-run', 'type' => 'assistant.navigation', 'payload' => ['navigation' => $applicantTargets]]),
        new XiamRunEvent(['run_id' => 'navigation-run', 'type' => 'assistant.message', 'payload' => [
            'content' => 'Visit [[navigate:applicant.office_inquiries]] or /admissions.',
            'navigation' => $applicantTargets,
        ]]),
    ];
    $projector = app(VercelUiMessageStreamProjector::class);
    $authorizedStream = implode('', iterator_to_array($projector->project($events, $applicant, 'applicant')));
    expect($authorizedStream)->toContain('data-xiam-navigation')
        ->and($authorizedStream)->not->toContain('/admissions');

    ApplicantOfficeInquiryDemoActor::query()->where('user_id', $applicant->id)->delete();
    $applicant->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    $staleStream = implode('', iterator_to_array($projector->project($events, $applicant, 'applicant')));
    expect(app(ApplicantOfficeInquiryAccess::class)->availability($applicant)['available'])->toBeFalse()
        ->and(array_column($targets->forActor($applicant, 'applicant'), 'id'))->not->toContain('applicant.office_inquiries')
        ->and($targets->reproject($applicant, ['applicant.office_inquiries'], 'applicant'))->toBe([])
        ->and($staleStream)->toContain('data-xiam-navigation')
        ->and($staleStream)->not->toContain($applicantTargets[array_search('applicant.office_inquiries', $applicantIds, true)]['href'])
        ->and($staleStream)->not->toContain('Office inquiries');
});

test('projectResponse handles unprefixed bracket targets and minor typos robustly', function (): void {
    seedE2Foundation();
    $this->seed(ApplicantOfficeInquiryDemoSeeder::class);

    $applicant = ApplicantOfficeInquiryDemoActor::query()
        ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)
        ->where('actor_type', ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT)
        ->firstOrFail()->user;
    $targets = app(XiamNavigationTargets::class);

    // 1. Unprefixed bracket token [[applicant.program_choice]]
    $res1 = $targets->projectResponse(
        'You can navigate directly to the [[applicant.program_choice]] page to review options.',
        $applicant,
        'applicant'
    );
    expect($res1['text'])->toBe('You can navigate directly to the Program choice page to review options.')
        ->and($res1['navigation'])->toHaveCount(1)
        ->and($res1['navigation'][0]['id'])->toBe('applicant.program_choice')
        ->and($res1['navigation'][0]['href'])->toBe('/applicant/program-choice');

    // 2. Minor typo fuzzy match [[applicant.program_chpice]]
    $res2 = $targets->projectResponse(
        'You can navigate directly to the [[applicant.program_chpice]] page to review options.',
        $applicant,
        'applicant'
    );
    expect($res2['text'])->toBe('You can navigate directly to the Program choice page to review options.')
        ->and($res2['navigation'])->toHaveCount(1)
        ->and($res2['navigation'][0]['id'])->toBe('applicant.program_choice');

    // 3. Standard [[navigate:applicant.program_choice]]
    $res3 = $targets->projectResponse(
        'You can navigate directly to the [[navigate:applicant.program_choice]] page to review options.',
        $applicant,
        'applicant'
    );
    expect($res3['text'])->toBe('You can navigate directly to the Program choice page to review options.')
        ->and($res3['navigation'])->toHaveCount(1)
        ->and($res3['navigation'][0]['id'])->toBe('applicant.program_choice');

    // 4. Unknown/invented bracket token stripped without leaking raw bracket syntax
    $res4 = $targets->projectResponse(
        'Check [[applicant.invented_nonexistent_dest]] for info.',
        $applicant,
        'applicant'
    );
    expect($res4['text'])->toBe('Check  for info.')
        ->and($res4['text'])->not->toContain('[[')
        ->and($res4['text'])->not->toContain(']]')
        ->and($res4['navigation'])->toBe([]);
});
