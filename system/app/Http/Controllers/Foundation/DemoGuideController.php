<?php

namespace App\Http\Controllers\Foundation;

use App\Foundation\DemoGuideAssets;
use App\Foundation\DemoGuideCatalog;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class DemoGuideController extends Controller
{
    public function index(DemoGuideCatalog $catalog, DemoGuideAssets $assets): Response
    {
        return Inertia::render('Foundation/DemoGuide', [
            'workflows' => $catalog->workflows($assets),
            'materials' => $assets->materials(),
            'can_control' => false,
            'preparation_url' => null,
        ]);
    }

    public function diagram(string $diagram, DemoGuideAssets $assets): HttpResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $asset = $assets->resolve($diagram, 'diagram');
        abort_if($asset === null, 404);

        return response($asset['content'], 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$asset['name'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function material(string $material, DemoGuideAssets $assets): HttpResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $asset = $assets->resolve($material, 'material');
        abort_if($asset === null, 404);

        return response($asset['content'], 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$asset['name'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
