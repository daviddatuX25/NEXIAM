<?php

use App\Assessment\Omr\NativeBundle;
use App\Authorization\CapabilityCatalog;
use App\Console\Commands\Assessment\OmrCanonicalParityCommand;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OmrDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

test('parity command passes on seeded blueprint versions and writes durable parity status', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new OmrDemoSeeder)->run();
    $exitCode = Artisan::call('omr:canonical-parity', ['--json' => true]);
    $output = Artisan::output();
    $disk = Storage::disk((string) config('omr.canonical.storage.disk', 'local'));

    expect($exitCode)->toBe(0)
        ->and($disk->exists(OmrCanonicalParityCommand::STATUS_PATH))->toBeTrue();

    $status = json_decode((string) $disk->get(OmrCanonicalParityCommand::STATUS_PATH), true);
    expect($status['passed'])->toBeTrue()
        ->and($status['versions'])->not->toBeEmpty();
});

test('builder route default remains old builder when flag is disabled', function (): void {
    config(['omr.canonical.enabled' => false]);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    $response = $this->actingAs($user)->get(route('setup.omr.templates.builder'));
    $response->assertOk();
});

test('builder route redirects to canonical workflow when flag is enabled and parity passed', function (): void {
    config(['omr.canonical.enabled' => true]);
    $disk = Storage::disk((string) config('omr.canonical.storage.disk', 'local'));
    $disk->put(OmrCanonicalParityCommand::STATUS_PATH, json_encode([
        'contract' => 'omr-canonical-parity.v1',
        'passed' => true,
        'checked_at' => now()->toIso8601String(),
    ]));

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    $response = $this->actingAs($user)->get(route('setup.omr.templates.builder'));
    $response->assertRedirect(route('setup.exam-materials.answer-sheets.index'));
});

test('historical markerless template scan path is unchanged', function (): void {
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 1);

    expect($bundle->hasMarkerPages())->toBeFalse()
        ->and($bundle->template['preProcessors'][0]['name'])->toBe('CropPage');
});
