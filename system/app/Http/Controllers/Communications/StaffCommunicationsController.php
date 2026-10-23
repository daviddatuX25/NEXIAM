<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communications;

use App\Communications\Actions\PublishAnnouncement;
use App\Communications\Actions\SaveAnnouncement;
use App\Communications\Actions\WithdrawAnnouncement;
use App\Communications\Queries\AnnouncementQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\CommandAnnouncementRequest;
use App\Http\Requests\Communications\SaveAnnouncementRequest;
use App\Http\Requests\Communications\WithdrawAnnouncementRequest;
use App\Models\Communications\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class StaffCommunicationsController extends Controller
{
    public function index(Request $request, AnnouncementQuery $query): Response
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'draft', 'scheduled', 'published', 'expired', 'withdrawn'])],
            'search' => ['nullable', 'string', 'max:120'],
            'placement' => ['sometimes', Rule::in(['all', 'general', 'program'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $props = $query->staffIndex($request->user(), [
            'status' => (string) ($filters['status'] ?? 'all'),
            'search' => (string) ($filters['search'] ?? ''),
            'placement' => (string) ($filters['placement'] ?? 'all'),
            'page' => (int) ($filters['page'] ?? 1),
        ]);
        abort_if($props['publishers'] === [], 403);

        return Inertia::render('Communications/Index', $props);
    }

    public function create(Request $request, AnnouncementQuery $query): Response
    {
        return Inertia::render('Communications/Create', $query->staffForm($request->user()));
    }

    public function edit(Request $request, Announcement $announcement, AnnouncementQuery $query): Response
    {
        return Inertia::render('Communications/Edit', $query->staffForm($request->user(), $announcement));
    }

    public function store(SaveAnnouncementRequest $request, SaveAnnouncement $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated());

        return Redirect::route('communications.index')->with('status', 'Announcement draft saved.');
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, SaveAnnouncement $save): RedirectResponse
    {
        $save->handle($request->user(), [...$request->validated(), 'announcement_id' => (int) $announcement->getKey()]);

        return Redirect::route('communications.index')->with('status', 'Announcement saved.');
    }

    public function publish(CommandAnnouncementRequest $request, Announcement $announcement, PublishAnnouncement $publish): RedirectResponse
    {
        $publish->handle($request->user(), $announcement, $request->validated());

        return Redirect::route('communications.index')->with('status', 'Announcement published.');
    }

    public function withdraw(WithdrawAnnouncementRequest $request, Announcement $announcement, WithdrawAnnouncement $withdraw): RedirectResponse
    {
        $withdraw->handle($request->user(), $announcement, $request->validated());

        return Redirect::route('communications.index')->with('status', 'Announcement withdrawn.');
    }
}
