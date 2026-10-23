<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

uses(RefreshDatabase::class);

/**
 * @return array{institution: Institution, other_institution: Institution, owner: User, other_owner: User}
 */
function makeDraftOwnershipScope(): array
{
    return [
        'institution' => Institution::query()->create([
            'code' => 'DRAFT-'.Str::upper(Str::random(10)),
            'name' => 'Draft Ownership Institution',
        ]),
        'other_institution' => Institution::query()->create([
            'code' => 'DRAFT-'.Str::upper(Str::random(10)),
            'name' => 'Other Draft Ownership Institution',
        ]),
        'owner' => User::factory()->create(),
        'other_owner' => User::factory()->create(),
    ];
}

function makeDraftOwnershipTemplate(Institution $institution, bool $isSynthetic, string $suffix): OmrAnswerSheetTemplate
{
    return OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SHEET-'.$suffix,
        'name' => 'Draft ownership sheet '.$suffix,
        'is_synthetic' => $isSynthetic,
    ]);
}

/**
 * @param  array{institution: Institution, other_institution: Institution, owner: User, other_owner: User}  $scope
 */
function makeOwnedDraft(array $scope, OmrAnswerSheetTemplate $template, bool $isSynthetic): OmrTemplateAuthoringDraft
{
    return OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => $scope['institution']->id,
        'is_synthetic' => $isSynthetic,
        'template_id' => $template->id,
        'code' => 'DRAFT-'.Str::upper(Str::random(10)),
        'name' => 'Owned draft fixture',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $scope['owner']->id,
    ]);
}

test('new drafts require institution ownership and explicit provenance at the database boundary', function (): void {
    $scope = makeDraftOwnershipScope();
    $now = now();

    expect(fn () => DB::table('omr_template_authoring_drafts')->insert([
        'institution_id' => null,
        'is_synthetic' => false,
        'code' => 'NO-INSTITUTION',
        'name' => 'No institution',
        'source_kind' => 'html',
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 1,
        'stage' => 'draft',
        'authoring_json' => '{}',
        'lock_version' => 0,
        'owner_user_id' => $scope['owner']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('omr_template_authoring_drafts')->insert([
        'institution_id' => $scope['institution']->id,
        'is_synthetic' => null,
        'code' => 'NO-PROVENANCE',
        'name' => 'No provenance',
        'source_kind' => 'html',
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 1,
        'stage' => 'draft',
        'authoring_json' => '{}',
        'lock_version' => 0,
        'owner_user_id' => $scope['owner']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});

test('draft owner and provenance cannot be changed through the model or direct database updates', function (): void {
    $scope = makeDraftOwnershipScope();
    $template = makeDraftOwnershipTemplate($scope['institution'], false, Str::upper(Str::random(8)));
    $draft = makeOwnedDraft($scope, $template, false);

    expect(fn () => $draft->forceFill(['institution_id' => $scope['other_institution']->id])->save())
        ->toThrow(LogicException::class);
    expect(fn () => $draft->fresh()->forceFill(['is_synthetic' => true])->save())
        ->toThrow(LogicException::class);
    expect(fn () => $draft->fresh()->forceFill(['owner_user_id' => $scope['other_owner']->id])->save())
        ->toThrow(LogicException::class);

    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draft->id)
        ->update(['institution_id' => $scope['other_institution']->id]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draft->id)
        ->update(['is_synthetic' => true]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draft->id)
        ->update(['owner_user_id' => $scope['other_owner']->id]))
        ->toThrow(QueryException::class);
});

test('draft linked template must match exact institution and provenance', function (): void {
    $scope = makeDraftOwnershipScope();
    $matchingTemplate = makeDraftOwnershipTemplate($scope['institution'], false, Str::upper(Str::random(8)));
    $otherInstitutionTemplate = makeDraftOwnershipTemplate($scope['other_institution'], false, Str::upper(Str::random(8)));
    $syntheticTemplate = makeDraftOwnershipTemplate($scope['institution'], true, Str::upper(Str::random(8)));

    $draft = makeOwnedDraft($scope, $matchingTemplate, false);

    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draft->id)
        ->update(['template_id' => $otherInstitutionTemplate->id]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draft->id)
        ->update(['template_id' => $syntheticTemplate->id]))
        ->toThrow(QueryException::class);

    expect(fn () => OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => $scope['institution']->id,
        'is_synthetic' => false,
        'template_id' => $syntheticTemplate->id,
        'code' => 'DRAFT-'.Str::upper(Str::random(10)),
        'name' => 'Mismatched provenance draft',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $scope['owner']->id,
    ]))->toThrow(LogicException::class);

    expect(makeOwnedDraft($scope, $matchingTemplate, false)->template_id)->toBe($matchingTemplate->id);
});

test('existing unclassified draft cannot be claimed by assigning a new institution owner', function (): void {
    $scope = makeDraftOwnershipScope();
    $now = now();
    DB::unprepared('DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_insert_guard');

    $draftId = DB::table('omr_template_authoring_drafts')->insertGetId([
        'institution_id' => null,
        'is_synthetic' => null,
        'template_id' => null,
        'code' => 'LEGACY-'.Str::upper(Str::random(8)),
        'name' => 'Unclassified legacy draft',
        'source_kind' => 'html',
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 1,
        'stage' => 'draft',
        'authoring_json' => '{}',
        'lock_version' => 0,
        'owner_user_id' => $scope['owner']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::unprepared(<<<'SQL'
        CREATE TRIGGER omr_template_authoring_drafts_identity_insert_guard
        BEFORE INSERT ON omr_template_authoring_drafts
        WHEN NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL
        BEGIN
            SELECT RAISE(ABORT, 'New OMR authoring drafts require an institution owner, user owner and provenance.');
        END
    SQL);

    expect(fn () => DB::table('omr_template_authoring_drafts')->where('id', $draftId)
        ->update(['institution_id' => $scope['institution']->id, 'is_synthetic' => false]))
        ->toThrow(QueryException::class);

    expect(fn () => OmrTemplateAuthoringDraft::query()->findOrFail($draftId)->save())
        ->toThrow(LogicException::class);
});
