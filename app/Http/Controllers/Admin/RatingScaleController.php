<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RatingScale;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingScaleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RatingScale::class);

        $ratingScales = RatingScale::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (RatingScale $rs) => [
                'id' => $rs->id,
                'name' => $rs->name,
                'ranges' => $rs->ranges,
                'is_default' => $rs->is_default,
            ]);

        return Inertia::render('Admin/RatingScales/Index', [
            'rating_scales' => $ratingScales,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RatingScale::class);

        return Inertia::render('Admin/RatingScales/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', RatingScale::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.min' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.max' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.label' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($data['is_default'])) {
            RatingScale::query()->update(['is_default' => false]);
        }

        $scale = RatingScale::create([
            'name' => $data['name'],
            'ranges' => $data['ranges'],
            'is_default' => $data['is_default'] ?? false,
        ]);

        app(AuditService::class)->log('rating_scale.created', RatingScale::class, $scale->id, [], $data);

        return redirect()->route('admin.rating-scales.index')
            ->with('success', 'Rating scale created.');
    }

    public function edit(RatingScale $ratingScale): Response
    {
        $this->authorize('update', $ratingScale);

        return Inertia::render('Admin/RatingScales/Edit', [
            'rating_scale' => [
                'id' => $ratingScale->id,
                'name' => $ratingScale->name,
                'ranges' => $ratingScale->ranges,
                'is_default' => $ratingScale->is_default,
            ],
        ]);
    }

    public function update(Request $request, RatingScale $ratingScale): RedirectResponse
    {
        $this->authorize('update', $ratingScale);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.min' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.max' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.label' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($data['is_default'])) {
            RatingScale::query()->update(['is_default' => false]);
        }

        $ratingScale->update([
            'name' => $data['name'],
            'ranges' => $data['ranges'],
            'is_default' => $data['is_default'] ?? false,
        ]);

        app(AuditService::class)->log('rating_scale.updated', RatingScale::class, $ratingScale->id, [], $data);

        return redirect()->route('admin.rating-scales.index')
            ->with('success', 'Rating scale updated.');
    }

    public function destroy(RatingScale $ratingScale): RedirectResponse
    {
        $this->authorize('delete', $ratingScale);

        app(AuditService::class)->log('rating_scale.deleted', RatingScale::class, $ratingScale->id, [], ['name' => $ratingScale->name]);

        $ratingScale->delete();

        return redirect()->route('admin.rating-scales.index')
            ->with('success', 'Rating scale deleted.');
    }
}
