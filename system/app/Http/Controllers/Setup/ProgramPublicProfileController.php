<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Communications\Actions\PublishProgramPublicProfile;
use App\Communications\Actions\SaveProgramPublicProfile;
use App\Communications\Actions\StageProgramPhoto;
use App\Communications\Queries\ProgramPublicProfileQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\PublishProgramPublicProfileRequest;
use App\Http\Requests\Communications\SaveProgramPublicProfileRequest;
use App\Http\Requests\Communications\StageProgramPhotoRequest;
use App\Models\Communications\ProgramPublicProfilePhoto;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ProgramPublicProfileController extends Controller
{
    public function edit(Program $program, Request $request, ProgramPublicProfileQuery $query): InertiaResponse
    {
        return Inertia::render('Setup/ProgramPublicDetails', $query->staffEditor($program, $this->actor($request)));
    }

    public function update(
        SaveProgramPublicProfileRequest $request,
        Program $program,
        SaveProgramPublicProfile $action,
        ProgramPublicProfileQuery $query,
    ): JsonResponse|RedirectResponse {
        $revision = $action->handle($this->actor($request), $program, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['profile' => $query->staffRevisionProfile($program, $revision, $this->actor($request))]);
        }

        return redirect()->route('setup.programs.public-details.edit', $program)->with('status', 'Program profile draft saved privately.');
    }

    public function publish(
        PublishProgramPublicProfileRequest $request,
        Program $program,
        PublishProgramPublicProfile $action,
        ProgramPublicProfileQuery $query,
    ): JsonResponse|RedirectResponse {
        $revision = $action->handle($this->actor($request), $program, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['profile' => $query->publishedRevisionProfile($program, $revision, $this->actor($request))]);
        }

        return redirect()->route('setup.programs.public-details.edit', $program)->with('status', 'The saved program profile revision is now published.');
    }

    public function upload(
        StageProgramPhotoRequest $request,
        Program $program,
        StageProgramPhoto $action,
        ProgramPublicProfileQuery $query,
    ): JsonResponse {
        $photo = $request->file('photo');
        abort_unless($photo instanceof UploadedFile, 422);
        $staged = $action->handle($this->actor($request), $program, (string) $request->validated('request_id'), $photo);

        return response()->json(['photo' => $query->stagedPhotoDto($program, $staged, $this->actor($request))]);
    }

    public function staffPhoto(Program $program, ProgramPublicProfilePhoto $photo, Request $request, ProgramPublicProfileQuery $query): Response
    {
        $image = $query->staffPhotoBytes($program, $photo, $this->actor($request));

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'],
            'Content-Length' => (string) strlen($image['bytes']),
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Program $program, Request $request, ProgramPublicProfileQuery $query): InertiaResponse
    {
        return Inertia::render('Setup/ProgramPublicDetailsPreview', $query->staffPreview($program, $this->actor($request)));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
