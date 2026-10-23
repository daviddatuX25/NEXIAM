<?php

use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\StagedMedia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

beforeEach(function (): void {
    Storage::fake('media-lifecycle');
    Schema::create('media_lifecycle_test_rows', function (Blueprint $table): void {
        $table->id();
        $table->string('value');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('media_lifecycle_test_rows');
});

test('staging writes media and returns its checksum and size', function (): void {
    $contents = 'durable media bytes';

    $staged = (new MediaStorageLifecycle)->stage(
        'media-lifecycle',
        'uploads/image.bin',
        $contents,
        'application/octet-stream',
    );

    Storage::disk('media-lifecycle')->assertExists('uploads/image.bin');
    expect($staged->disk)->toBe('media-lifecycle')
        ->and($staged->path)->toBe('uploads/image.bin')
        ->and($staged->sha256)->toBe(hash('sha256', $contents))
        ->and($staged->byteSize)->toBe(strlen($contents))
        ->and($staged->mime)->toBe('application/octet-stream');
});

test('successful commit preserves staged media and deletes displaced media after the transaction', function (): void {
    $lifecycle = new MediaStorageLifecycle;
    $staged = $lifecycle->stage('media-lifecycle', 'uploads/new.bin', 'new bytes', 'application/octet-stream');
    Storage::disk('media-lifecycle')->put('uploads/old.bin', 'old bytes');
    $transactionLevelDuringDomainWork = null;
    $oldPathExistedDuringDomainWork = false;

    $result = $lifecycle->commit($staged, function (StagedMedia $media) use (&$transactionLevelDuringDomainWork, &$oldPathExistedDuringDomainWork): array {
        $transactionLevelDuringDomainWork = DB::transactionLevel();
        $oldPathExistedDuringDomainWork = Storage::disk('media-lifecycle')->exists('uploads/old.bin');
        DB::table('media_lifecycle_test_rows')->insert(['value' => 'committed']);

        return ['displaced_paths' => ['uploads/old.bin']];
    });

    expect($transactionLevelDuringDomainWork)->toBeGreaterThan(0)
        ->and($oldPathExistedDuringDomainWork)->toBeTrue()
        ->and(DB::transactionLevel())->toBe(0)
        ->and(DB::table('media_lifecycle_test_rows')->where('value', 'committed')->exists())->toBeTrue()
        ->and($result)->toBe(['displaced_paths' => ['uploads/old.bin']]);
    Storage::disk('media-lifecycle')->assertExists('uploads/new.bin');
    Storage::disk('media-lifecycle')->assertMissing('uploads/old.bin');
});

test('failed domain transaction rolls back and compensates staged media without deleting old media', function (): void {
    $lifecycle = new MediaStorageLifecycle;
    $staged = $lifecycle->stage('media-lifecycle', 'uploads/new.bin', 'new bytes', 'application/octet-stream');
    Storage::disk('media-lifecycle')->put('uploads/old.bin', 'old bytes');

    expect(fn () => $lifecycle->commit($staged, function (StagedMedia $media): never {
        DB::table('media_lifecycle_test_rows')->insert(['value' => 'rolled-back']);

        throw new RuntimeException('Domain transaction failed.');
    }))->toThrow(RuntimeException::class, 'Domain transaction failed.');

    expect(DB::transactionLevel())->toBe(0)
        ->and(DB::table('media_lifecycle_test_rows')->where('value', 'rolled-back')->exists())->toBeFalse();
    Storage::disk('media-lifecycle')->assertMissing('uploads/new.bin');
    Storage::disk('media-lifecycle')->assertExists('uploads/old.bin');
});

test('quiet deletion ignores a non-existent path', function (): void {
    expect(fn () => (new MediaStorageLifecycle)->deleteQuietly('media-lifecycle', 'missing.bin'))
        ->not->toThrow(Throwable::class);
});
