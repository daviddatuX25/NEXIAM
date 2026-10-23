<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Communications\PublicHomepageMedia;
use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\Foundation\Institution;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class HomepageMediaController extends Controller
{
    public function show(PublicHomepageMedia $media): Response
    {
        $institution = Institution::current() ?? Institution::query()->where('code', 'SYN-INST-001')->first();
        abort_unless($institution !== null && (int) $media->institution_id === (int) $institution->id, 404);
        abort_unless(in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        abort_unless($media->status === 'ready', 404);

        $usedByActiveHero = PublicHomepageSection::query()->where('institution_id', $institution->id)
            ->where('key', 'hero')->where('is_active', true)->where('image_id', $media->id)->exists();
        $usedByActiveStory = PublicHomepageStory::query()->where('institution_id', $institution->id)
            ->where('is_active', true)->where('image_id', $media->id)
            ->whereHas('section', fn ($query) => $query->where('key', 'campus_stories')->where('is_active', true))->exists();
        abort_unless($usedByActiveHero || $usedByActiveStory, 404);

        $contents = Storage::disk($media->storage_disk)->get($media->storage_path);
        abort_unless(is_string($contents), 404);

        return response($contents)->header('Content-Type', $media->mime_type)
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
