<?php

declare(strict_types=1);

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @param array<string, mixed> $value */
function testKitPgSchemaHash(array $value): string
{
    $sort = static function (array $input) use (&$sort): array {
        if (! array_is_list($input)) {
            ksort($input, SORT_STRING);
        }
        foreach ($input as $key => $child) {
            if (is_array($child)) {
                $input[$key] = $sort($child);
            }
        }

        return $input;
    };

    return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** @return array<string, mixed> */
function testKitPgSchemaFixture(User $actor, Institution $institution): array
{
    $now = now();
    $suffix = Str::upper(Str::random(8));
    $timestamps = ['created_at' => $now, 'updated_at' => $now];

    $area = AptitudeArea::query()->create([
        'code' => 'PGKIT-'.$suffix,
        'name' => 'Synthetic schema fixture '.$suffix,
        'max_items' => 1,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $instrumentId = (int) DB::table('test_instruments')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'PGKIT-TI-'.$suffix,
        'is_synthetic' => true,
        ...$timestamps,
    ]);
    $profileId = (int) DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'status' => 'draft',
        'display_name' => 'Synthetic PostgreSQL schema profile '.$suffix,
        'publisher_name' => 'Synthetic schema fixture publisher '.$suffix,
        'form_code' => 'SCHEMA-'.$suffix,
        'edition' => '1',
        'language' => 'en',
        'source_reference' => 'Synthetic schema fixture only; no external source is represented.',
        'permitted_use_reference' => 'PostgreSQL constraint tests only.',
        'lock_version' => 1,
        'content_sha256' => null,
        'created_by_user_id' => $actor->id,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Standard OMR paper procedure',
        ...$timestamps,
    ]);
    $subtestCode = 'SCHEMA-'.$suffix;
    $itemCode = 'SCHEMA-'.$suffix;
    $subtestId = (int) DB::table('test_instrument_subtests')->insertGetId([
        'test_instrument_version_id' => $profileId,
        'code' => $subtestCode,
        'name' => 'Synthetic schema item',
        'display_order' => 1,
        'response_mode' => 'keyed_items',
        'item_order_policy' => 'fixed',
        ...$timestamps,
    ]);
    $itemId = (int) DB::table('test_instrument_items')->insertGetId([
        'test_instrument_version_id' => $profileId,
        'test_instrument_subtest_id' => $subtestId,
        'item_code' => $itemCode,
        'local_number' => '1',
        'display_order' => 1,
        'is_scored' => true,
        'option_tokens' => json_encode(['A', 'B'], JSON_THROW_ON_ERROR),
        ...$timestamps,
    ]);
    $metricCode = 'RAW-'.$suffix;
    $metricId = (int) DB::table('test_instrument_metrics')->insertGetId([
        'test_instrument_version_id' => $profileId,
        'code' => $metricCode,
        'name' => 'Raw Score',
        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
        'test_instrument_subtest_id' => $subtestId,
        'unit' => 'count',
        'min_raw_value' => '0.000000',
        'max_raw_value' => '1.000000',
        'precision_scale' => 0,
        'is_required' => true,
        'required_output_scales' => json_encode(['percentile_rank'], JSON_THROW_ON_ERROR),
        'procedure_reference' => null,
        'construct_aptitude_area_id' => $area->id,
        ...$timestamps,
    ]);
    $profileModel = TestInstrumentVersion::query()->findOrFail($profileId);
    $profileValidator = new TestInstrumentProfileValidator;
    $profileNormalized = $profileValidator->normalizeVersion($profileModel);
    $profileValidator->assertApprovable($profileNormalized, $profileNormalized['profile']);
    $profileHash = $profileValidator->digest($profileNormalized);
    // This demo-only approved state exists solely for the receipt schema's exact profile-content foreign key; it is not institutional source review or approval.
    DB::table('test_instrument_versions')->where('id', $profileId)->update([
        'status' => 'approved',
        'source_reviewed_by_user_id' => $actor->id,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $actor->id,
        'approved_at' => $now,
        'content_sha256' => $profileHash,
        'updated_at' => $now,
    ]);

    $templateId = (int) DB::table('omr_answer_sheet_templates')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'PGKIT-SHEET-'.$suffix,
        'name' => 'Synthetic PostgreSQL schema sheet '.$suffix,
        'is_synthetic' => true,
        ...$timestamps,
    ]);
    $relativeDir = sprintf('omr-bundles/institutions/%d/templates/%d/v1', $institution->id, $templateId);
    $sheetJson = [
        'relative_dir' => $relativeDir,
        'fixture' => 'synthetic-schema-only',
        'template_code' => 'PGKIT-SHEET-'.$suffix,
        'version' => 1,
        'pages' => [['page_no' => 1, 'width' => 2480, 'height' => 3508]],
        'template' => [
            'pages' => [['page_no' => 1, 'width' => 2480, 'height' => 3508]],
        ],
        'config' => ['config' => $suffix],
        'manifest' => [
            'bundle_version' => 'synthetic-schema-fixture-v1',
            'marker_profile' => ['code' => 'SYNTHETIC'],
        ],
        'files' => [],
    ];
    $sheetHash = testKitPgSchemaHash($sheetJson);
    $templateJsonHash = testKitPgSchemaHash(['template_code' => 'PGKIT-SHEET-'.$suffix, 'version' => 1]);
    $reviewManifest = [
        'template_code' => 'PGKIT-SHEET-'.$suffix,
        'template_version' => 1,
        'pages' => [
            [
                'page_no' => 1,
                'page_dimensions' => ['width' => 2480, 'height' => 3508],
            ],
        ],
        'fields' => [
            [
                'page' => 1,
                'label' => 'Q1',
                'option' => 'A',
                'pixel' => ['x' => 100, 'y' => 100, 'w' => 20, 'h' => 20],
            ],
            [
                'page' => 1,
                'label' => 'Q1',
                'option' => 'B',
                'pixel' => ['x' => 100, 'y' => 130, 'w' => 20, 'h' => 20],
            ],
        ],
    ];
    $reviewManifestHash = testKitPgSchemaHash($reviewManifest);
    $sheetVersionId = (int) DB::table('omr_answer_sheet_template_versions')->insertGetId([
        'template_id' => $templateId,
        'test_instrument_version_id' => null,
        'version' => 1,
        'status' => 'draft',
        'registration_mode' => OmrRegistrationMode::DocumentBound->value,
        'qr_mode' => OmrQrMode::Disabled->value,
        'page_count' => 1,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => json_encode([], JSON_THROW_ON_ERROR),
        'provider_config_json' => json_encode([], JSON_THROW_ON_ERROR),
        'config_hash' => testKitPgSchemaHash(['config' => $suffix]),
        'template_json_hash' => $templateJsonHash,
        'native_bundle_version' => 'synthetic-schema-fixture-v1',
        'native_bundle_json' => json_encode($sheetJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'native_bundle_hash' => $sheetHash,
        'review_manifest_json' => json_encode($reviewManifest, JSON_THROW_ON_ERROR),
        'review_manifest_hash' => $reviewManifestHash,
        'authoring_manifest' => json_encode(['source_kind' => 'system_designed'], JSON_THROW_ON_ERROR),
        'engine_name' => 'synthetic-schema-fixture',
        'engine_commit' => 'synthetic-only',
        'patch_identity' => 'synthetic-schema-fixture-v1',
        ...$timestamps,
    ]);
    $sheetPageId = (int) DB::table('omr_answer_sheet_pages')->insertGetId([
        'template_version_id' => $sheetVersionId,
        'page_no' => 1,
        'width' => 2480,
        'height' => 3508,
        'page_manifest_json' => json_encode($reviewManifest, JSON_THROW_ON_ERROR),
        'page_manifest_hash' => $reviewManifestHash,
    ]);
    $areaId = (int) DB::table('omr_template_areas')->insertGetId([
        'template_version_id' => $sheetVersionId,
        'aptitude_area_id' => $area->id,
        'display_order' => 1,
        'max_items' => 1,
        'region_x' => 0.1,
        'region_y' => 0.1,
        'region_w' => 0.8,
        'region_h' => 0.8,
        'projection_manifest_hash' => $reviewManifestHash,
    ]);
    $questionId = (int) DB::table('omr_template_questions')->insertGetId([
        'template_area_id' => $areaId,
        'page_no' => 1,
        'question_no' => 1,
        'region_x' => 0.2,
        'region_y' => 0.2,
        'region_w' => 0.2,
        'region_h' => 0.2,
        'projection_manifest_hash' => $reviewManifestHash,
    ]);
    foreach (['A', 'B'] as $index => $token) {
        DB::table('omr_template_question_options')->insert([
            'template_question_id' => $questionId,
            'code' => $token,
            'display_order' => $index + 1,
            'region_x' => 0.2,
            'region_y' => 0.3 + ($index * 0.1),
            'region_w' => 0.05,
            'region_h' => 0.05,
            'projection_manifest_hash' => $reviewManifestHash,
        ]);
    }
    $mappingId = (int) DB::table('omr_template_item_mappings')->insertGetId([
        'template_version_id' => $sheetVersionId,
        'test_instrument_version_id' => $profileId,
        'test_instrument_subtest_id' => $subtestId,
        'test_instrument_item_id' => $itemId,
        'native_label' => 'Q1',
        'page_no' => 1,
        'global_position' => 1,
        ...$timestamps,
    ]);
    $sourceAssetId = (int) DB::table('omr_template_assets')->insertGetId([
        'template_version_id' => $sheetVersionId,
        'kind' => 'reference_page',
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'test-kit-pg-schema/'.$suffix.'/source.json',
        'mime' => 'application/json',
        'bytes' => 2,
        'sha256' => hash('sha256', '{}'),
        'metadata' => json_encode(['synthetic_fixture' => true], JSON_THROW_ON_ERROR),
        ...$timestamps,
    ]);
    $secondaryRelativeDir = sprintf('omr-bundles/institutions/%d/templates/%d/v2', $institution->id, $templateId);
    $secondarySheetJson = [
        ...$sheetJson,
        'relative_dir' => $secondaryRelativeDir,
        'version' => 2,
    ];
    $secondarySheetHash = testKitPgSchemaHash($secondarySheetJson);
    $secondaryTemplateJsonHash = testKitPgSchemaHash(['template_code' => 'PGKIT-SHEET-'.$suffix, 'version' => 2]);
    $secondaryReviewManifest = [
        'template_code' => 'PGKIT-SHEET-'.$suffix,
        'template_version' => 2,
        'pages' => [
            [
                'page_no' => 1,
                'page_dimensions' => ['width' => 2480, 'height' => 3508],
            ],
        ],
        'fields' => [
            [
                'page' => 1,
                'label' => 'Q1',
                'option' => 'A',
                'pixel' => ['x' => 100, 'y' => 100, 'w' => 20, 'h' => 20],
            ],
            [
                'page' => 1,
                'label' => 'Q1',
                'option' => 'B',
                'pixel' => ['x' => 100, 'y' => 130, 'w' => 20, 'h' => 20],
            ],
        ],
    ];
    $secondaryReviewManifestHash = testKitPgSchemaHash($secondaryReviewManifest);
    $secondarySheetId = (int) DB::table('omr_answer_sheet_template_versions')->insertGetId([
        'template_id' => $templateId,
        'test_instrument_version_id' => $profileId,
        'version' => 2,
        'status' => 'draft',
        'registration_mode' => OmrRegistrationMode::DocumentBound->value,
        'qr_mode' => OmrQrMode::Disabled->value,
        'page_count' => 1,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => json_encode([], JSON_THROW_ON_ERROR),
        'provider_config_json' => json_encode([], JSON_THROW_ON_ERROR),
        'config_hash' => testKitPgSchemaHash(['config' => $suffix, 'version' => 2]),
        'template_json_hash' => $secondaryTemplateJsonHash,
        'native_bundle_version' => 'synthetic-schema-fixture-v1',
        'native_bundle_json' => json_encode($secondarySheetJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'native_bundle_hash' => $secondarySheetHash,
        'review_manifest_json' => json_encode($secondaryReviewManifest, JSON_THROW_ON_ERROR),
        'review_manifest_hash' => $secondaryReviewManifestHash,
        'authoring_manifest' => json_encode(['source_kind' => 'system_designed'], JSON_THROW_ON_ERROR),
        'engine_name' => 'synthetic-schema-fixture',
        'engine_commit' => 'synthetic-only',
        'patch_identity' => 'synthetic-schema-fixture-v1',
        ...$timestamps,
    ]);
    $secondaryPageId = (int) DB::table('omr_answer_sheet_pages')->insertGetId([
        'template_version_id' => $secondarySheetId,
        'page_no' => 1,
        'width' => 2480,
        'height' => 3508,
        'page_manifest_json' => json_encode($secondaryReviewManifest, JSON_THROW_ON_ERROR),
        'page_manifest_hash' => $secondaryReviewManifestHash,
    ]);
    $secondaryMappingId = (int) DB::table('omr_template_item_mappings')->insertGetId([
        'template_version_id' => $secondarySheetId,
        'test_instrument_version_id' => $profileId,
        'test_instrument_subtest_id' => $subtestId,
        'test_instrument_item_id' => $itemId,
        'native_label' => 'Q1',
        'page_no' => 1,
        'global_position' => 1,
        ...$timestamps,
    ]);
    $secondarySourceAssetId = (int) DB::table('omr_template_assets')->insertGetId([
        'template_version_id' => $secondarySheetId,
        'kind' => 'reference_page',
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'test-kit-pg-schema/'.$suffix.'/secondary-source.json',
        'mime' => 'application/json',
        'bytes' => 2,
        'sha256' => hash('sha256', '{}'),
        'metadata' => json_encode(['synthetic_fixture' => true], JSON_THROW_ON_ERROR),
        ...$timestamps,
    ]);
    $secondaryAreaId = (int) DB::table('omr_template_areas')->insertGetId([
        'template_version_id' => $secondarySheetId,
        'aptitude_area_id' => $area->id,
        'display_order' => 1,
        'max_items' => 1,
        'region_x' => 0.1,
        'region_y' => 0.1,
        'region_w' => 0.8,
        'region_h' => 0.8,
        'projection_manifest_hash' => $secondaryReviewManifestHash,
        ...$timestamps,
    ]);
    $secondaryQuestionId = (int) DB::table('omr_template_questions')->insertGetId([
        'template_area_id' => $secondaryAreaId,
        'page_no' => 1,
        'question_no' => 1,
        'region_x' => 0.2,
        'region_y' => 0.2,
        'region_w' => 0.2,
        'region_h' => 0.2,
        'projection_manifest_hash' => $secondaryReviewManifestHash,
        ...$timestamps,
    ]);
    foreach (['A', 'B'] as $index => $token) {
        DB::table('omr_template_question_options')->insert([
            'template_question_id' => $secondaryQuestionId,
            'code' => $token,
            'display_order' => $index + 1,
            'region_x' => 0.2,
            'region_y' => 0.3 + ($index * 0.1),
            'region_w' => 0.05,
            'region_h' => 0.05,
            'projection_manifest_hash' => $secondaryReviewManifestHash,
            ...$timestamps,
        ]);
    }

    $bookletId = (int) DB::table('omr_question_booklets')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'PGKIT-BOOKLET-'.$suffix,
        'name' => 'Synthetic PostgreSQL schema booklet '.$suffix,
        'is_synthetic' => true,
        ...$timestamps,
    ]);
    $bookletVersionId = (int) DB::table('omr_question_booklet_versions')->insertGetId([
        'booklet_id' => $bookletId,
        'template_version_id' => null,
        'test_instrument_version_id' => null,
        'version' => 1,
        'status' => 'draft',
        'authoring_mode' => 'answer_keys_only',
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => testKitPgSchemaHash(['draft-key' => $suffix]),
        ...$timestamps,
    ]);
    $bookletItemHash = testKitPgSchemaHash(['booklet' => $bookletId, 'question' => $questionId, 'answer' => 'A']);
    $bookletItemId = (int) DB::table('omr_booklet_items')->insertGetId([
        'booklet_version_id' => $bookletVersionId,
        'aptitude_area_id' => $area->id,
        'test_instrument_item_id' => $itemId,
        'authored_position' => 1,
        'encrypted_content' => Crypt::encryptString('synthetic schema-only item'),
        'correct_option_code' => 'A',
        'content_hash' => $bookletItemHash,
        ...$timestamps,
    ]);
    DB::table('omr_question_booklet_questions')->insert([
        'booklet_version_id' => $bookletVersionId,
        'template_question_id' => $questionId,
        'aptitude_area_id' => $area->id,
        'question_position' => 1,
        'correct_option_code' => 'A',
        ...$timestamps,
    ]);
    $identityRows = [
        [
            'test_instrument_item_id' => $itemId,
            'aptitude_area_id' => $area->id,
            'area_position' => 1,
            'global_position' => 1,
            'correct_option_code' => 'A',
        ],
    ];
    $setAnswerKeyHash = hash('sha256', json_encode($identityRows, JSON_THROW_ON_ERROR));
    $setOrderHash = hash('sha256', json_encode(array_map(
        static fn (array $row): array => array_diff_key($row, ['correct_option_code' => true]),
        $identityRows,
    ), JSON_THROW_ON_ERROR));
    $bookletContentHash = hash('sha256', json_encode([['item_id' => $bookletItemId, 'content_hash' => $bookletItemHash]], JSON_THROW_ON_ERROR));
    $setId = (int) DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersionId,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'synthetic-schema-v1',
        'source_content_hash' => $bookletContentHash,
        'order_hash' => $setOrderHash,
        'answer_key_hash' => $setAnswerKeyHash,
        ...$timestamps,
    ]);
    $setItemId = (int) DB::table('omr_booklet_set_items')->insertGetId([
        'booklet_set_id' => $setId,
        'booklet_item_id' => $bookletItemId,
        'aptitude_area_id' => $area->id,
        'test_instrument_item_id' => $itemId,
        'area_position' => 1,
        'global_position' => 1,
        'correct_option_code' => 'A',
        'content_hash' => $bookletItemHash,
        ...$timestamps,
    ]);
    DB::table('omr_booklet_sets')->where('id', $setId)->update([
        'status' => 'finalized',
        'finalized_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('omr_question_booklet_versions')->where('id', $bookletVersionId)->update([
        'status' => 'finalized',
        'finalized_at' => $now,
        'finalized_by_user_id' => $actor->id,
        'content_hash' => $bookletContentHash,
        'answer_key_hash' => $setAnswerKeyHash,
        'updated_at' => $now,
    ]);

    DB::table('omr_answer_sheet_template_versions')->where('id', $sheetVersionId)->update([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'updated_at' => $now,
    ]);

    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'PGKIT-'.$suffix,
        'name' => 'Synthetic PostgreSQL schema kit '.$suffix,
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $manifest = [
        'schema_fixture' => true,
        'provenance' => 'synthetic_only',
        'sheet' => ['template_id' => $templateId, 'version_id' => $sheetVersionId, 'material_sha256' => $sheetHash],
        'booklet' => ['booklet_id' => $bookletId, 'version_id' => $bookletVersionId, 'content_sha256' => $bookletContentHash],
        'set_a' => ['id' => $setId, 'order_sha256' => $setOrderHash, 'key_sha256' => $setAnswerKeyHash],
        'profile' => ['version_id' => $profileId, 'content_sha256' => $profileHash],
    ];
    $manifestHash = testKitPgSchemaHash($manifest);
    $versionId = (int) DB::table('test_kit_versions')->insertGetId([
        'test_kit_id' => $kit->id,
        'version_number' => 1,
        'predecessor_version_id' => null,
        'template_version_id' => $sheetVersionId,
        'booklet_version_id' => $bookletVersionId,
        'booklet_set_id' => $setId,
        'test_instrument_version_id' => $profileId,
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => $setOrderHash,
        'booklet_set_answer_key_hash' => $setAnswerKeyHash,
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'synthetic-schema-canonicalizer-v1',
        'manifest_json' => json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'manifest_sha256' => $manifestHash,
        'status' => 'candidate',
        'revision' => 1,
        'created_by_user_id' => $actor->id,
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'template_id' => $templateId,
        'booklet_id' => $bookletId,
        'test_instrument_id' => $instrumentId,
        ...$timestamps,
    ]);

    return [
        'institution' => $institution,
        'actor' => $actor,
        'kit' => $kit,
        'version' => TestKitVersion::query()->findOrFail($versionId),
        'profile' => TestInstrumentVersion::query()->findOrFail($profileId),
        'profile_content_sha256' => $profileHash,
        'template_id' => $templateId,
        'sheet' => OmrAnswerSheetTemplateVersion::query()->findOrFail($sheetVersionId),
        'secondary_sheet_id' => $secondarySheetId,
        'secondary_mapping_id' => $secondaryMappingId,
        'secondary_source_asset_id' => $secondarySourceAssetId,
        'secondary_page_id' => $secondaryPageId,
        'sheet_page_id' => $sheetPageId,
        'area_id' => $area->id,
        'template_area_id' => $areaId,
        'template_question_id' => $questionId,
        'mapping_id' => $mappingId,
        'source_asset_id' => $sourceAssetId,
        'booklet' => OmrQuestionBookletVersion::query()->findOrFail($bookletVersionId),
        'booklet_id' => $bookletId,
        'set' => (object) ['id' => $setId, 'order_hash' => $setOrderHash, 'answer_key_hash' => $setAnswerKeyHash, 'source_content_hash' => $bookletContentHash],
        'set_item_id' => $setItemId,
        'item_id' => $itemId,
        'manifest' => $manifest,
        'manifest_sha256' => $manifestHash,
    ];
}
