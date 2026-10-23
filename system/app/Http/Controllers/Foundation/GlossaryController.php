<?php

namespace App\Http\Controllers\Foundation;

use App\Foundation\CapstoneGlossaryCatalog;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class GlossaryController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Foundation/Glossary', CapstoneGlossaryCatalog::projection());
    }
}
