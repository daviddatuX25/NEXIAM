<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communications;

use App\Communications\Queries\AnnouncementQuery;
use App\Communications\Queries\PublicPolicyQuery;
use App\Http\Controllers\Controller;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Inertia\Inertia;
use Inertia\Response;

final class PublicPoliciesController extends Controller
{
    public function show(int $policy, PublicPolicyQuery $query, AnnouncementQuery $announcements): Response
    {
        $detail = $query->eligiblePublicDetail($policy);
        $ticker = $detail['owningProgram'] === null
            ? $announcements->publicHome()
            : $announcements->publicProgram($detail['owningProgram']['code']);

        return Inertia::render('Public/Policy', [
            'institution' => Institution::current()->toPublicArray(), 'alerts' => $ticker['alerts'] ?? [],
            ...$detail,
        ]);
    }

    public function forProgram(string $code, PublicPolicyQuery $query, AnnouncementQuery $announcements): Response
    {
        $context = $announcements->publicProgram($code);
        $program = Program::query()->whereKey($context['program']['id'])->firstOrFail();

        return Inertia::render('Public/ProgramPolicies', [
            'program' => $context['program'], 'alerts' => $context['alerts'] ?? [],
            'policies' => $query->publicPolicies($program),
            'institution' => Institution::current()->toPublicArray(),
        ]);
    }
}
