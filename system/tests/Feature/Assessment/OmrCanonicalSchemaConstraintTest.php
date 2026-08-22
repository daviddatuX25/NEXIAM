<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('canonical tables enforce draft stage and asset exactly-one-owner constraints', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('PostgreSQL CHECK constraints are exercised in the Compose runtime.');
    }

    $actor = User::factory()->create();
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'CANONICAL-CONSTRAINT', 'name' => 'Canonical constraint']);

    expect(fn () => DB::table('omr_template_authoring_drafts')->insert([
        'template_id' => $template->id,
        'code' => 'invalid-stage',
        'name' => 'Invalid stage',
        'source_kind' => 'html',
        'stage' => 'not-a-stage',
        'authoring_json' => '{}',
        'lock_version' => 0,
        'owner_user_id' => $actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => OmrTemplateAsset::query()->create([
        'kind' => OmrTemplateAsset::KIND_SOURCE,
        'disk' => 'local',
        'path' => 'invalid/source.html',
        'mime' => 'text/html',
        'bytes' => 1,
        'sha256' => hash('sha256', 'invalid'),
    ]))->toThrow(QueryException::class);
});

test('marker profile code and version and blank display codes remain unique', function (): void {
    $actor = User::factory()->create();
    $values = [
        'code' => 'constraint.marker',
        'version' => 1,
        'family' => 'test',
        'asset_path' => 'marker.jpg',
        'asset_sha256' => hash('sha256', 'marker'),
        'matching_config' => [],
        'created_by_user_id' => $actor->id,
    ];
    OmrMarkerProfile::query()->create($values);

    expect(fn () => OmrMarkerProfile::query()->create($values))->toThrow(QueryException::class);
});
