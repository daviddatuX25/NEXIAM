<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function testKitSealedMaterialFixture(): array
{
    $institution = Institution::query()->create([
        'code' => 'SEAL-'.Str::upper(Str::random(8)),
        'name' => 'Component seal fixture',
    ]);
    $actor = User::factory()->create();
    $sheetFamily = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SEAL-SHEET-'.Str::upper(Str::random(6)),
        'name' => 'Seal test sheet',
        'is_synthetic' => true,
    ]);
    $sheet = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $sheetFamily->id,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
    ]);
    $otherDraft = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $sheetFamily->id,
        'version' => 2,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
    ]);
    $bookletFamily = OmrQuestionBooklet::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SEAL-BOOKLET-'.Str::upper(Str::random(6)),
        'name' => 'Seal test booklet',
        'is_synthetic' => true,
    ]);
    $booklet = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $bookletFamily->id,
        'template_version_id' => $sheet->id,
        'test_instrument_version_id' => null,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', 'draft-key'),
    ]);
    $area = AptitudeArea::query()->create([
        'code' => 'SEAL-AREA-'.Str::upper(Str::random(5)),
        'name' => 'Seal test area',
        'max_items' => 2,
        'display_order' => 1,
        'is_active' => true,
    ]);

    return compact('actor', 'institution', 'sheet', 'otherDraft', 'booklet', 'area');
}

test('raw writes cannot alter sealed sheet and booklet material while staged relationships remain writable', function (): void {
    $fixture = testKitSealedMaterialFixture();
    $sheet = $fixture['sheet'];
    $otherDraft = $fixture['otherDraft'];
    $booklet = $fixture['booklet'];

    DB::table('omr_answer_sheet_template_versions')->where('id', $sheet->id)->update([
        'native_bundle_hash' => hash('sha256', 'staged-native-bundle'),
        'native_bundle_json' => json_encode(['template' => [], 'config' => [], 'manifest' => [], 'files' => []], JSON_THROW_ON_ERROR),
    ]);

    $areaId = DB::table('omr_template_areas')->insertGetId([
        'template_version_id' => $sheet->id,
        'aptitude_area_id' => $fixture['area']->id,
        'display_order' => 1,
        'max_items' => 2,
        'region_x' => 0,
        'region_y' => 0,
        'region_w' => 1,
        'region_h' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherAreaId = DB::table('omr_template_areas')->insertGetId([
        'template_version_id' => $otherDraft->id,
        'aptitude_area_id' => $fixture['area']->id,
        'display_order' => 1,
        'max_items' => 2,
        'region_x' => 0,
        'region_y' => 0,
        'region_w' => 1,
        'region_h' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $questionId = DB::table('omr_template_questions')->insertGetId([
        'template_area_id' => $areaId,
        'question_no' => 1,
        'page_no' => 1,
        'region_x' => 0,
        'region_y' => 0,
        'region_w' => 1,
        'region_h' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $setId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $booklet->id,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'booklet-set-v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $setItemId = DB::table('omr_booklet_set_items')->insertGetId([
        'booklet_set_id' => $setId,
        'booklet_item_id' => null,
        'aptitude_area_id' => $fixture['area']->id,
        'area_position' => 1,
        'global_position' => 1,
        'correct_option_code' => 'A',
        'content_hash' => hash('sha256', 'staged-key'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->assertDatabaseHas('omr_template_questions', ['id' => $questionId]);
    $this->assertDatabaseHas('omr_booklet_set_items', ['id' => $setItemId]);

    DB::table('omr_answer_sheet_template_versions')->where('id', $sheet->id)->update([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
    ]);
    DB::table('omr_question_booklet_versions')->where('id', $booklet->id)->update([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'finalized_at' => now(),
        'finalized_by_user_id' => $fixture['actor']->id,
        'content_hash' => hash('sha256', 'finalized-content'),
        'answer_key_hash' => hash('sha256', 'finalized-answer-key'),
    ]);

    expect(fn () => DB::table('omr_template_areas')->where('id', $areaId)->update(['max_items' => 9]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_questions')->where('id', $questionId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_areas')->where('id', $areaId)->update(['template_version_id' => $otherDraft->id]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_areas')->insert([
            'template_version_id' => $sheet->id,
            'aptitude_area_id' => $fixture['area']->id,
            'display_order' => 2,
            'max_items' => 1,
            'region_x' => 0,
            'region_y' => 0,
            'region_w' => 1,
            'region_h' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_areas')->where('id', $otherAreaId)->update(['template_version_id' => $sheet->id]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_assets')->insert([
            'template_version_id' => $sheet->id,
            'kind' => 'source',
            'disk' => 'private',
            'path' => 'compiled/source.pdf',
            'mime' => 'application/pdf',
            'bytes' => 1,
            'sha256' => hash('sha256', 'source'),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_template_assets')->insert([
            'template_version_id' => $sheet->id,
            'kind' => 'print_pdf',
            'disk' => 'private',
            'path' => 'compiled/overlay.pdf',
            'mime' => 'application/pdf',
            'bytes' => 1,
            'sha256' => hash('sha256', 'overlay'),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(DB::table('omr_template_assets')->insertGetId([
            'template_version_id' => $sheet->id,
            'kind' => 'digital_proof',
            'disk' => 'private',
            'path' => 'compiled/proof.png',
            'mime' => 'image/png',
            'bytes' => 1,
            'sha256' => hash('sha256', 'proof'),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toBeInt()
        ->and(fn () => DB::table('omr_booklet_set_items')->where('id', $setItemId)->update(['correct_option_code' => 'B']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_booklet_set_items')->where('id', $setItemId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_booklet_set_items')->insert([
            'booklet_set_id' => $setId,
            'aptitude_area_id' => $fixture['area']->id,
            'area_position' => 2,
            'global_position' => 2,
            'correct_option_code' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_question_booklet_versions')->where('id', $booklet->id)->update(['answer_key_hash' => hash('sha256', 'tampered')]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_question_booklet_versions')->where('id', $booklet->id)->update(['finalized_by_user_id' => null]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_question_booklet_versions')->where('id', $booklet->id)->update(['finalized_at' => null]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('omr_question_booklet_versions')->where('id', $booklet->id)->update(['status' => OmrQuestionBookletVersion::STATUS_DRAFT]))
        ->toThrow(QueryException::class);
    $compiledSheet = $sheet->fresh();
    $finalizedBooklet = $booklet->fresh();

    expect(fn () => $compiledSheet->forceFill(['native_bundle_hash' => hash('sha256', 'tampered')])->save())
        ->toThrow(RuntimeException::class, 'Compiled answer-sheet versions are immutable.')
        ->and(fn () => $compiledSheet->delete())
        ->toThrow(RuntimeException::class, 'Compiled answer-sheet versions are immutable.')
        ->and(fn () => $finalizedBooklet->forceFill(['content_hash' => hash('sha256', 'tampered')])->save())
        ->toThrow(RuntimeException::class, 'Finalized Booklet versions are immutable.')
        ->and(fn () => $finalizedBooklet->delete())
        ->toThrow(RuntimeException::class, 'Finalized Booklet versions are immutable.');
});
