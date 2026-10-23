<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Ai\Knowledge\PublicFaqQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicFaqController
{
    public function index(Request $request, PublicFaqQuery $query): Response
    {
        $category = $request->query('category');
        $office = $request->query('office');
        $search = $request->query('search');

        return Inertia::render('Public/Faqs', [
            'faqs' => $query->getPublicFaqs(
                category: is_string($category) ? $category : null,
                office: is_string($office) ? $office : null,
                search: is_string($search) ? $search : null,
            ),
            'categories' => $query->getCategories(),
            'selectedCategory' => is_string($category) ? $category : null,
            'search' => is_string($search) ? $search : '',
        ]);
    }
}
