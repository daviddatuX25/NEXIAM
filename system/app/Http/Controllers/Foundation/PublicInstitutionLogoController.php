<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\Foundation\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class PublicInstitutionLogoController extends Controller
{
    public function show(Request $request): Response
    {
        $institution = Institution::current();

        if (empty($institution->logo_path) || ! Storage::disk('public')->exists($institution->logo_path)) {
            abort(404, 'No institutional logo uploaded.');
        }

        $etag = '"'.($institution->logo_sha256 ?? md5($institution->logo_path)).'"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            ]);
        }

        $contents = Storage::disk('public')->get($institution->logo_path);
        abort_unless(is_string($contents), 404);

        $mime = $institution->logo_mime ?? Storage::disk('public')->mimeType($institution->logo_path) ?? 'image/webp';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
