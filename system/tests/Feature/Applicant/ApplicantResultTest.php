<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Foundation\AptitudeArea;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Storage;

function persistApplicantResultPdf(ResultDocument $document): string
{
    Storage::fake('sensitive');
    $bytes = "%PDF-1.7\nApplicant result fixture\n%%EOF\n";
    $path = 'results/'.$document->admission_attempt_id.'/result-'.$document->id.'.pdf';
    app(SensitiveAssetStore::class)->store($bytes, $path);

    $document->update([
        'artifact_manifest' => [
            'pdf' => [
                'status' => 'ready',
                'path' => $path,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
            ],
        ],
    ]);

    return $bytes;
}

function createApplicantResultScenario(
    string $email,
    string $releaseMode = ReleaseResult::MODE_ONLINE,
    bool $isCurrent = true,
    ?string $decisionType = null,
    string $lastName = 'Applicant',
    array $scoreSnapshot = [],
): array {
    seedE2Foundation();
    $result = submitSyntheticApplicant($email, $lastName === 'Applicant' ? 'Applicant'.substr(md5($email), 0, 8) : $lastName);
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );
    $attempt = $result['attempt'];

    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
        ?? User::factory()->create();

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'version' => 1,
        'status' => ResultDocument::STATUS_READY,
        'template_version' => '1.0.0',
        'checksum' => hash('sha256', 'test-doc'),
        'snapshot_payload_json' => ['scores' => $scoreSnapshot],
    ]);
    $pdfBytes = persistApplicantResultPdf($document);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    $release = ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'release_version' => 1,
        'mode' => $releaseMode,
        'released_by_user_id' => $counselor->id,
        'is_current' => $isCurrent,
        'released_at' => now(),
        'idempotency_key' => 'rel-'.md5($email),
    ]);

    $encounter = null;
    if ($decisionType !== null) {
        ConsultationDecision::query()->create([
            'admission_attempt_id' => $attempt->id,
            'counselor_user_id' => $counselor->id,
            'decision_type' => $decisionType,
            'decided_at' => now(),
            'idempotency_key' => 'dec-'.md5($email).'-'.$decisionType,
            'notes' => 'Qualified for preferred degree.',
        ]);

        if ($releaseMode === ReleaseResult::MODE_F2F && in_array($decisionType, ['approve', 'modify', 'override'], true)) {
            $encounter = ConsultationEncounter::query()->create([
                'institution_id' => $attempt->institution_id,
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'result_document_version' => $document->version,
                'counselor_user_id' => $counselor->id,
                'medium' => ConsultationEncounter::MEDIUM_F2F,
                'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
                'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                'admitted_at' => now()->subMinutes(5),
                'closed_at' => now(),
                'idempotency_key' => 'encounter-'.md5($email),
            ]);
        }
    }

    return [$attempt, $applicant, $document, $release, $pdfBytes, $encounter];
}

test('guest is redirected to login from results', function (): void {
    $this->get(route('applicant.results.show'))
        ->assertRedirect(route('applicant.login'));
});

test('applicant without an attempt sees denied state', function (): void {
    seedE2Foundation();
    $user = User::query()->create([
        'name' => 'Applicant without attempt',
        'email' => 'result-no-attempt@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'denied')
            ->where('can_download_pdf', false));
});

test('unreleased attempt is not ready and PDF download is forbidden', function (): void {
    [$attempt, $applicant] = createApplicantResultScenario('result-unreleased@example.test', releaseMode: ReleaseResult::MODE_ONLINE, isCurrent: false);
    $release = ResultRelease::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $release->delete();

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'not_ready')
            ->where('score_profile', null));

    $this->get(route('applicant.results.download-pdf'))
        ->assertForbidden();
});

test('online release unlocks only its persisted stored PDF with download and inline print dispositions', function (): void {
    [$attempt, $applicant, , , $pdfBytes] = createApplicantResultScenario('result-online@example.test');
    ResultSheetTemplate::query()->update(['is_active' => false]);

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unlocked')
            ->where('release.mode', ReleaseResult::MODE_ONLINE)
            ->where('attempt', $attempt->reference_code)
            ->where('can_download_pdf', true));

    $this->get(route('applicant.results.download-pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="result-sheet-'.$attempt->reference_code.'.pdf"')
        ->assertSee($pdfBytes, false);

    $this->get(route('applicant.results.download-pdf', ['presentation' => 'inline']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="result-sheet-'.$attempt->reference_code.'.pdf"')
        ->assertSee($pdfBytes, false);
});

test('released aptitude rows come from the issued snapshot instead of the expanded live profile', function (): void {
    $scores = [
        ['area_code' => 'VERBAL', 'domain' => 'Verbal Aptitude', 'converted' => '76'],
        ['area_code' => 'QUANT', 'domain' => 'Numerical Aptitude', 'converted' => '96'],
        ['area_code' => 'LOGIC', 'domain' => 'General Ability', 'converted' => '84'],
        ['area_code' => 'SPATIAL', 'domain' => 'Spatial Aptitude', 'converted' => '99'],
        ['area_code' => 'MEMORY', 'domain' => 'Perceptual Aptitude', 'converted' => '88'],
        ['area_code' => 'LANGUAGE', 'domain' => 'Manual Dexterity', 'converted' => '90'],
    ];
    [, $applicant] = createApplicantResultScenario('result-frozen-aptitudes@example.test', scoreSnapshot: $scores);
    foreach ([
        'PROFILE_VERBAL' => 'Profile Verbal Reasoning',
        'PROFILE_QUANT' => 'Profile Quantitative Reasoning',
    ] as $code => $name) {
        AptitudeArea::query()->firstOrCreate(
            ['code' => $code, 'version' => 1],
            ['name' => $name, 'is_active' => true],
        );
    }
    AptitudeArea::query()->where('code', 'VERBAL')->update(['name' => 'Changed live verbal label']);

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'unlocked')
            ->where('score_profile', [
                ['area_code' => 'VERBAL', 'area_name' => 'Verbal Aptitude', 'score' => '76', 'scale' => null, 'available' => true],
                ['area_code' => 'QUANT', 'area_name' => 'Numerical Aptitude', 'score' => '96', 'scale' => null, 'available' => true],
                ['area_code' => 'LOGIC', 'area_name' => 'General Ability', 'score' => '84', 'scale' => null, 'available' => true],
                ['area_code' => 'SPATIAL', 'area_name' => 'Spatial Aptitude', 'score' => '99', 'scale' => null, 'available' => true],
                ['area_code' => 'MEMORY', 'area_name' => 'Perceptual Aptitude', 'score' => '88', 'scale' => null, 'available' => true],
                ['area_code' => 'LANGUAGE', 'area_name' => 'Manual Dexterity', 'score' => '90', 'scale' => null, 'available' => true],
            ]));
});

test('released score rows preserve zero and never substitute raw values for a missing converted score', function (): void {
    [, $applicant] = createApplicantResultScenario('result-frozen-zero@example.test', scoreSnapshot: [
        ['area_code' => 'VERBAL', 'domain' => 'Verbal Aptitude', 'converted' => '0', 'raw' => '17', 'scale' => 'percentile_rank'],
        ['area_code' => 'QUANT', 'domain' => 'Numerical Aptitude', 'raw' => '29', 'pct' => 96],
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('score_profile', [
            ['area_code' => 'VERBAL', 'area_name' => 'Verbal Aptitude', 'score' => '0', 'scale' => 'percentile_rank', 'available' => true],
            ['area_code' => 'QUANT', 'area_name' => 'Numerical Aptitude', 'score' => null, 'scale' => null, 'available' => false],
        ])->missing('score_profile.0.raw')
            ->missing('score_profile.0.source')
            ->missing('score_profile.0.result_set_id'));
});

test('F2F release without a consultation decision is held and PDF download is forbidden', function (): void {
    [$attempt, $applicant] = createApplicantResultScenario('result-f2f-held@example.test', releaseMode: ReleaseResult::MODE_F2F, decisionType: null);

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'held_f2f')
            ->where('score_profile', null));

    $this->get(route('applicant.results.download-pdf'))
        ->assertForbidden();
});

test('F2F release unlocks after approve, modify, or override decisions and completed encounters', function (): void {
    foreach (['approve', 'modify', 'override'] as $decisionType) {
        [$attempt, $applicant, , , $pdfBytes, $encounter] = createApplicantResultScenario(
            "result-f2f-{$decisionType}@example.test",
            releaseMode: ReleaseResult::MODE_F2F,
            decisionType: $decisionType,
            lastName: 'F2f'.ucfirst($decisionType),
        );

        expect($encounter)->not->toBeNull();
        $this->actingAs($applicant)
            ->get(route('applicant.results.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applicant/Results')
                ->where('page_state', 'unlocked')
                ->where('is_advised', true)
                ->where('can_download_pdf', true));

        $this->get(route('applicant.results.download-pdf'))
            ->assertOk()
            ->assertSee($pdfBytes, false);
    }
});

test('a completed F2F result without issuance truthfully remains preparing and is not downloadable', function (): void {
    [$attempt, $applicant, , $release] = createApplicantResultScenario(
        'result-f2f-pending@example.test',
        releaseMode: ReleaseResult::MODE_F2F,
        decisionType: 'approve',
    );
    $release->delete();

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'preparing_pdf')
            ->where('can_download_pdf', false));

    $this->get(route('applicant.results.download-pdf'))->assertForbidden();
});

test('tampered and missing stored artifacts are not advertised or served', function (): void {
    foreach (['tampered', 'missing'] as $case) {
        [, $applicant, $document] = createApplicantResultScenario("result-artifact-{$case}@example.test");
        $manifest = $document->artifact_manifest;
        if ($case === 'tampered') {
            $manifest['pdf']['sha256'] = str_repeat('0', 64);
        } else {
            $manifest['pdf']['path'] .= '.missing';
        }
        $document->update(['artifact_manifest' => $manifest]);

        $this->actingAs($applicant)
            ->get(route('applicant.results.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applicant/Results')
                ->where('page_state', 'unavailable')
                ->where('can_download_pdf', false));

        $this->get(route('applicant.results.download-pdf'))->assertNotFound();
    }
});

test('superseded and physical-only releases cannot expose a portal PDF', function (): void {
    [, $applicant, $document, $release] = createApplicantResultScenario('result-superseded@example.test');
    $replacement = ResultDocument::query()->create([
        'admission_attempt_id' => $document->admission_attempt_id,
        'version' => 2,
        'status' => ResultDocument::STATUS_READY,
        'template_version' => '1.0.0',
        'checksum' => hash('sha256', 'replacement-doc'),
    ]);
    $attempt = $release->admissionAttempt;
    $attempt->forceFill(['current_result_document_id' => $replacement->id])->save();

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unavailable')
            ->where('can_download_pdf', false));
    $this->get(route('applicant.results.download-pdf'))->assertForbidden();

    [, $physicalApplicant, , $physicalRelease] = createApplicantResultScenario('result-physical-only@example.test');
    $physicalRelease->update(['channel' => 'physical']);
    $this->actingAs($physicalApplicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unavailable')
            ->where('can_download_pdf', false));
    $this->get(route('applicant.results.download-pdf'))->assertForbidden();

    [, $legacyPhysicalApplicant, , $legacyPhysicalRelease] = createApplicantResultScenario('result-legacy-physical-only@example.test');
    $legacyPhysicalRelease->update([
        'channel' => null,
        'physical_handover_at' => now(),
    ]);
    $this->actingAs($legacyPhysicalApplicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unavailable')
            ->where('can_download_pdf', false));
    $this->get(route('applicant.results.download-pdf'))->assertForbidden();
});

test('automatic F2F portal access is bound to the latest approved decision and completed encounter', function (): void {
    [$attempt, $applicant, , $release, , $encounter] = createApplicantResultScenario(
        'result-f2f-automatic@example.test',
        releaseMode: ReleaseResult::MODE_F2F,
        decisionType: 'approve',
    );
    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('id')->firstOrFail();
    $release->forceFill([
        'mode' => ReleaseResult::MODE_ONLINE,
        'channel' => 'portal',
        'consultation_decision_id' => $decision->id,
        'consultation_encounter_id' => $encounter->id,
    ])->save();

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unlocked')
            ->where('can_download_pdf', true));

    foreach (['refer', 'defer'] as $decisionType) {
        ConsultationDecision::query()->create([
            'admission_attempt_id' => $attempt->id,
            'counselor_user_id' => $decision->counselor_user_id,
            'decision_type' => $decisionType,
            'idempotency_key' => 'result-f2f-latest-'.$decisionType,
            'notes' => 'Follow-up required.',
        ]);

        $this->get(route('applicant.results.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applicant/Results')
                ->where('page_state', 'unavailable')
                ->where('can_download_pdf', false));
        $this->get(route('applicant.results.download-pdf'))->assertForbidden();
    }

    $release->delete();
    $this->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unavailable')
            ->where('can_download_pdf', false));
});

test('portal issuance remains available alongside a separate physical release', function (): void {
    [$attempt, $applicant, $document, $portalRelease] = createApplicantResultScenario('result-dual-channel@example.test');
    $portalRelease->update(['channel' => 'portal']);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'release_version' => 2,
        'mode' => ReleaseResult::MODE_F2F,
        'channel' => 'physical',
        'released_by_user_id' => $portalRelease->released_by_user_id,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'result-dual-physical-channel',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unlocked')
            ->where('can_download_pdf', true));
});

test('retracted automatic F2F portal issuance does not reappear as preparing', function (): void {
    [$attempt, $applicant, , $release, , $encounter] = createApplicantResultScenario(
        'result-f2f-retracted@example.test',
        releaseMode: ReleaseResult::MODE_F2F,
        decisionType: 'approve',
    );
    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('id')->firstOrFail();
    $release->forceFill([
        'mode' => ReleaseResult::MODE_ONLINE,
        'channel' => 'portal',
        'consultation_decision_id' => $decision->id,
        'consultation_encounter_id' => $encounter->id,
        'is_current' => false,
        'unreleased_at' => now(),
    ])->save();

    $this->actingAs($applicant)
        ->get(route('applicant.results.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Results')
            ->where('page_state', 'unavailable')
            ->where('can_download_pdf', false));
    $this->get(route('applicant.results.download-pdf'))->assertForbidden();
});
test('terminal and unrecoverable PDF preparation failures are unavailable rather than permanently preparing', function (): void {
    foreach (['failed', 'unpreparable'] as $case) {
        [, $applicant, $document, $release] = createApplicantResultScenario(
            "result-f2f-preparation-{$case}@example.test",
            releaseMode: ReleaseResult::MODE_F2F,
            decisionType: 'approve',
        );
        $release->delete();
        $document->update([
            'artifact_manifest' => [
                'pdf' => [
                    'status' => $case === 'failed' ? 'failed' : 'pending',
                ],
            ],
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.results.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applicant/Results')
                ->where('page_state', 'unavailable')
                ->where('can_download_pdf', false));

        $this->get(route('applicant.results.download-pdf'))->assertForbidden();
    }
});
