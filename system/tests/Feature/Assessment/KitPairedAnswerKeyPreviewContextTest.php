<?php

use App\Http\Controllers\Setup\KitPairedAnswerKeyPreviewController;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => (int) Institution::current()->id,
        'code' => 'PREVIEW-CONTEXT-'.Str::upper(Str::random(8)),
        'name' => 'Paired preview context test',
        'is_synthetic' => true,
        'created_by_user_id' => (int) $this->actor->id,
    ]);
    $this->controller = (new ReflectionClass(KitPairedAnswerKeyPreviewController::class))->newInstanceWithoutConstructor();
});

function previewContextPreparation(TestKit $kit, int $revision = 1): TestKitPreparation
{
    return TestKitPreparation::query()->create([
        'test_kit_id' => (int) $kit->id,
        'state' => TestKitPreparation::STATE_PREPARING,
        'revision' => $revision,
        'created_by_user_id' => (int) $kit->created_by_user_id,
        'updated_by_user_id' => (int) $kit->created_by_user_id,
    ]);
}

it('rejects a raster URL from a preparation that has been replaced', function (): void {
    $oldPreparation = previewContextPreparation($this->kit, 3);
    $oldPreparationId = (int) $oldPreparation->id;
    $oldPreparation->delete();
    $currentPreparation = previewContextPreparation($this->kit, 1);
    $request = Request::create('/preview', 'GET', ['preparation_id' => $oldPreparationId]);
    $method = new ReflectionMethod(KitPairedAnswerKeyPreviewController::class, 'preparationForRequest');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($this->controller, $request, $this->kit))
        ->toThrow(ModelNotFoundException::class);
    expect($currentPreparation->id)->not->toBe($oldPreparationId);
});

it('rejects stale composition revisions and component digests before paired media resolution', function (): void {
    $request = Request::create('/preview', 'GET', [
        'preparation_id' => 12,
        'preparation_revision' => 8,
        'composition_revision' => 4,
        'component_vector_sha256' => 'old-vector-digest',
    ]);
    $preparation = new TestKitPreparation;
    $preparation->forceFill(['id' => 12, 'revision' => 9]);
    $resolved = ['composition_revision' => 5, 'component_vector_sha256' => 'current-vector-digest'];
    $method = new ReflectionMethod(KitPairedAnswerKeyPreviewController::class, 'assertPreparationContext');
    $method->setAccessible(true);

    try {
        $method->invoke($this->controller, $request, $preparation, $resolved);
        test()->fail('A stale revision and component vector must not be accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

it('rejects a sealed preview URL whose composition or manifest digest no longer matches', function (): void {
    $request = Request::create('/preview', 'GET', [
        'composition_revision' => 4,
        'composition_sha256' => 'old-composition-digest',
        'manifest_sha256' => 'old-manifest-digest',
    ]);
    $version = new TestKitVersion;
    $version->forceFill(['id' => 31, 'test_kit_id' => (int) $this->kit->id]);
    $resolved = [
        'composition_revision' => 4,
        'composition_sha256' => 'current-composition-digest',
        'manifest_sha256' => 'current-manifest-digest',
    ];
    $method = new ReflectionMethod(KitPairedAnswerKeyPreviewController::class, 'assertVersionContext');
    $method->setAccessible(true);

    try {
        $method->invoke($this->controller, $request, $version, $resolved);
        test()->fail('A stale sealed composition URL must not resolve to the current key context.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});
