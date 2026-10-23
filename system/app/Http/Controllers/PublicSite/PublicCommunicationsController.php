<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Communications\Queries\AnnouncementQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicCommunicationsController extends Controller
{
    public function announcements(Request $request, AnnouncementQuery $query): Response
    {
        $validated = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return Inertia::render('Public/Announcements', $query->publicIndex((int) ($validated['page'] ?? 1)));
    }

    public function program(string $code, AnnouncementQuery $query): Response
    {
        return Inertia::render('Public/Program', $query->publicProgram($code));
    }

    public function programAnnouncements(Request $request, string $code, AnnouncementQuery $query): Response
    {
        $validated = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return Inertia::render('Public/Announcements', $query->publicProgramAnnouncements($code, (int) ($validated['page'] ?? 1)));
    }

    public function announcement(int $announcement, AnnouncementQuery $query): Response
    {
        return Inertia::render('Public/Announcement', $query->publicDetail($announcement));
    }

    public function policies(AnnouncementQuery $query): Response
    {
        return Inertia::render('Public/Policies', $query->publicPoliciesPage());
    }

    public function privacyPolicy(AnnouncementQuery $query): Response
    {
        return Inertia::render('Public/PrivacyPolicy', $query->publicPrivacyPolicy());
    }
}
