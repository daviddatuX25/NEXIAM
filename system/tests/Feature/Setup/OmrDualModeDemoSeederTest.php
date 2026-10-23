<?php

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OmrDualModeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');
});

test('dual-mode demo seeder creates stable one- and two-page policy drafts without compiling them', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());

    $seeder = new OmrDualModeDemoSeeder;
    $seeder->run();

    $drafts = OmrTemplateAuthoringDraft::query()
        ->whereIn('code', ['OMR-DUAL-DOC-1P', 'OMR-DUAL-DOC-2P', 'OMR-DUAL-FID-1P', 'OMR-DUAL-FID-2P'])
        ->orderBy('code')
        ->get();
    $draftIds = $drafts->pluck('id')->all();

    expect($drafts->map(static fn (OmrTemplateAuthoringDraft $draft): array => [
        $draft->code,
        $draft->registration_mode->value,
        $draft->qr_mode->value,
        (int) $draft->requested_page_count,
        $draft->stage,
    ])->all())->toBe([
        ['OMR-DUAL-DOC-1P', OmrRegistrationMode::DocumentBound->value, OmrQrMode::Disabled->value, 1, OmrTemplateAuthoringDraft::STAGE_DRAFT],
        ['OMR-DUAL-DOC-2P', OmrRegistrationMode::DocumentBound->value, OmrQrMode::Disabled->value, 2, OmrTemplateAuthoringDraft::STAGE_DRAFT],
        ['OMR-DUAL-FID-1P', OmrRegistrationMode::FiducialAnchored->value, OmrQrMode::Signed->value, 1, OmrTemplateAuthoringDraft::STAGE_DRAFT],
        ['OMR-DUAL-FID-2P', OmrRegistrationMode::FiducialAnchored->value, OmrQrMode::Signed->value, 2, OmrTemplateAuthoringDraft::STAGE_DRAFT],
    ])->and($drafts->pluck('owner_user_id')->unique()->all())->toBe([
        User::query()->where('email', 'guidance.counselor@nexiam.test')->value('id'),
    ]);

    $seeder->run();

    expect(OmrTemplateAuthoringDraft::query()
        ->whereIn('code', ['OMR-DUAL-DOC-1P', 'OMR-DUAL-DOC-2P', 'OMR-DUAL-FID-1P', 'OMR-DUAL-FID-2P'])
        ->orderBy('code')
        ->pluck('id')
        ->all())->toBe($draftIds)
        ->and(OmrAnswerSheetTemplateVersion::query()
            ->where('registration_mode', OmrRegistrationMode::DocumentBound->value)
            ->where('qr_mode', OmrQrMode::Disabled->value)
            ->exists())->toBeFalse();
});
