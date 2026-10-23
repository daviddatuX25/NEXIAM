<?php

use App\Assessment\Omr\Canonical\CanonicalOmrFixture;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OmrDiagnosticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');
});

it('keeps normal and test foundation seeds limited to the approved canonical lineage', function (bool $testSeeders): void {
    $seeder = new DatabaseSeeder;
    $seeder->run($testSeeders ? DatabaseSeeder::testSeeders() : null);

    expect(OmrTemplateAuthoringDraft::query()->pluck('code')->all())->toBe([
        CanonicalOmrFixture::STABLE_CODE,
    ])->and(OmrAnswerSheetTemplate::query()->where('code', 'like', 'SYN-%')->exists())->toBeFalse()
        ->and(ExamSession::query()->where('code', 'EX-OMR-CANONICAL')->exists())->toBeFalse();
})->with([
    'test foundation seeders' => true,
    'normal foundation seeders' => false,
]);

it('creates synthetic OMR profile and scanner fixtures only when explicitly requested', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new OmrDiagnosticDemoSeeder)->run();

    expect(OmrTemplateAuthoringDraft::query()->where('code', 'CANONICAL-PRESET')->exists())->toBeTrue()
        ->and(OmrAnswerSheetTemplate::query()->where('code', 'SYN-OMR-PROFILE-150')->exists())->toBeTrue()
        ->and(ExamSession::query()->where('code', 'EX-OMR-CANONICAL')->exists())->toBeTrue();
});

it('does not mutate an existing approved canonical draft on reseed', function (): void {
    $seeder = new DatabaseSeeder;
    $seeder->run(DatabaseSeeder::testSeeders());

    $draft = OmrTemplateAuthoringDraft::query()
        ->where('code', CanonicalOmrFixture::STABLE_CODE)
        ->firstOrFail();
    $snapshot = $draft->only(['id', 'owner_user_id', 'stage', 'lock_version', 'authoring_json']);
    $updatedAt = $draft->getRawOriginal('updated_at');

    $seeder->run(DatabaseSeeder::testSeeders());
    $draft->refresh();

    expect($draft->only(array_keys($snapshot)))->toBe($snapshot)
        ->and($draft->getRawOriginal('updated_at'))->toBe($updatedAt)
        ->and(OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->count())->toBe(1);
});

it('does not mutate canonical rows when the canonical page is opened', function (): void {
    $this->withoutVite();
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->firstOrFail();
    $snapshot = $draft->only(['owner_user_id', 'stage', 'lock_version', 'authoring_json']);
    $updatedAt = $draft->getRawOriginal('updated_at');

    $this->actingAs($actor)->get(route('setup.exam-materials.answer-sheets.index'))->assertOk();
    $draft->refresh();

    expect($draft->only(array_keys($snapshot)))->toBe($snapshot)
        ->and($draft->getRawOriginal('updated_at'))->toBe($updatedAt);
});
