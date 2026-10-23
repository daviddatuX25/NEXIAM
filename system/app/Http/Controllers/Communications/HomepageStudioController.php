<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communications;

use App\Communications\Actions\ReorderHomepageStories;
use App\Communications\Actions\SaveHomepageHero;
use App\Communications\Actions\SaveHomepageStory;
use App\Communications\Actions\StageHomepageImage;
use App\Communications\Actions\ToggleHomepageStory;
use App\Communications\HomepageAuthorizer;
use App\Communications\Queries\HomepageContentQuery;
use App\Http\Controllers\Controller;
use App\Models\Communications\PublicHomepageMedia;
use App\Models\Communications\PublicHomepageStory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class HomepageStudioController extends Controller
{
    public function index(Request $request, HomepageAuthorizer $authorizer, HomepageContentQuery $query): Response
    {
        $institutionId = $authorizer->assertCanManage($request->user());

        return Inertia::render('Communications/Homepage/Index', $query->studio($institutionId));
    }

    public function updateHero(Request $request, SaveHomepageHero $save): RedirectResponse
    {
        $save->handle($request->user(), $request->all());

        return Redirect::back()->with('status', 'Homepage hero saved.');
    }

    public function hero(Request $request, HomepageAuthorizer $authorizer, HomepageContentQuery $query): Response
    {
        return $this->index($request, $authorizer, $query);
    }

    public function stageImage(Request $request, StageHomepageImage $stage): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp']]);

        try {
            $media = $stage->handle($request->user(), $request->file('image'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The image could not be processed. Please verify the file and try again.',
            ], 500);
        }

        return response()->json([
            'media' => [
                'id' => (int) $media->id,
                'src' => '/communications/homepage/images/'.$media->id,
                'alt' => '',
                'source' => null,
                'width' => $media->width,
                'height' => $media->height,
            ],
        ], 201);
    }

    public function showPrivateImage(Request $request, PublicHomepageMedia $media, HomepageAuthorizer $authorizer): \Illuminate\Http\Response
    {
        $institutionId = $authorizer->assertCanManage($request->user());
        abort_unless((int) $media->institution_id === $institutionId, 404);
        $contents = Storage::disk($media->storage_disk)->get($media->storage_path);
        abort_unless(is_string($contents), 404);

        return response($contents)->header('Content-Type', $media->mime_type)
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function staffImage(Request $request, PublicHomepageMedia $media, HomepageAuthorizer $authorizer): \Illuminate\Http\Response
    {
        return $this->showPrivateImage($request, $media, $authorizer);
    }

    public function storeStory(Request $request, SaveHomepageStory $save): RedirectResponse
    {
        $save->handle($request->user(), $request->all());

        return Redirect::back()->with('status', 'Campus story created.');
    }

    public function createStory(Request $request, HomepageAuthorizer $authorizer, HomepageContentQuery $query): Response
    {
        return $this->index($request, $authorizer, $query);
    }

    public function updateStory(Request $request, PublicHomepageStory $story, SaveHomepageStory $save): RedirectResponse
    {
        $save->handle($request->user(), $request->all(), $story);

        return Redirect::back()->with('status', 'Campus story saved.');
    }

    public function editStory(Request $request, PublicHomepageStory $story, HomepageAuthorizer $authorizer, HomepageContentQuery $query): Response
    {
        return $this->index($request, $authorizer, $query);
    }

    public function toggleStory(Request $request, PublicHomepageStory $story, ToggleHomepageStory $toggle): RedirectResponse
    {
        $toggle->handle($request->user(), $story);

        return Redirect::back()->with('status', 'Campus story visibility updated.');
    }

    public function toggleStoryActive(Request $request, PublicHomepageStory $story, ToggleHomepageStory $toggle): RedirectResponse
    {
        return $this->toggleStory($request, $story, $toggle);
    }

    public function reorderStories(Request $request, ReorderHomepageStories $reorder): RedirectResponse
    {
        $reorder->handle($request->user(), $request->all());

        return Redirect::back()->with('status', 'Campus stories reordered.');
    }
}
