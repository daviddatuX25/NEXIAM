<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Communications\Queries\ProgramPublicProfileQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ProgramPublicProfileMediaController extends Controller
{
    public function show(string $code, int $photo, ProgramPublicProfileQuery $query): Response
    {
        $image = $query->publicPhotoBytes($code, $photo);

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'],
            'Content-Length' => (string) strlen($image['bytes']),
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
