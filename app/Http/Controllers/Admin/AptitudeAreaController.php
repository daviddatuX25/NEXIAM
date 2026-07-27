<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAptitudeAreaRequest;
use App\Http\Requests\UpdateAptitudeAreaRequest;
use App\Models\AptitudeArea;
use App\Services\AuditService;
use App\Services\FormulaEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AptitudeAreaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AptitudeArea::class);

        $aptitudeAreas = AptitudeArea::query()
            ->withCount('percentileConversions')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AptitudeArea $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'code' => $a->code,
                'description' => $a->description,
                'max_items' => $a->max_items,
                'formula' => $a->formula,
                'scoring_method' => $a->scoring_method,
                'display_order' => $a->display_order,
                'is_active' => $a->is_active,
                'percentile_conversions_count' => $a->percentile_conversions_count,
            ]);

        return Inertia::render('Admin/AptitudeAreas/Index', [
            'aptitude_areas' => $aptitudeAreas,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AptitudeArea::class);

        return Inertia::render('Admin/AptitudeAreas/Create', [
            'enable_normalized_scores' => \App\Models\SystemSetting::enableNormalizedScores(),
        ]);
    }

    public function store(StoreAptitudeAreaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $area = AptitudeArea::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'max_items' => (int) $data['max_items'],
            'formula' => $data['formula'] ?? null,
            'scoring_method' => $data['scoring_method'] ?? 'formula',
            'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (($data['scoring_method'] ?? 'formula') === 'conversion_table' && ! empty($data['conversion_table'])) {
            foreach ($data['conversion_table'] as $row) {
                $area->percentileConversions()->create([
                    'raw_score' => $row['raw_score'],
                    'percentile_output' => $row['percentile_output'],
                ]);
            }
        }

        app(AuditService::class)->log('aptitude_area.created', AptitudeArea::class, $area->id, [], $data);

        return redirect()->route('admin.aptitude-areas.index')
            ->with('success', 'Aptitude area created.');
    }

    public function edit(AptitudeArea $aptitudeArea): Response
    {
        $this->authorize('update', $aptitudeArea);

        $aptitudeArea->load('percentileConversions');

        return Inertia::render('Admin/AptitudeAreas/Edit', [
            'enable_normalized_scores' => \App\Models\SystemSetting::enableNormalizedScores(),
            'aptitude_area' => [
                'id' => $aptitudeArea->id,
                'name' => $aptitudeArea->name,
                'code' => $aptitudeArea->code,
                'description' => $aptitudeArea->description ?? '',
                'max_items' => $aptitudeArea->max_items,
                'formula' => $aptitudeArea->formula ?? '',
                'scoring_method' => $aptitudeArea->scoring_method ?? 'formula',
                'percentile_conversions' => $aptitudeArea->percentileConversions
                    ->sortBy('raw_score')
                    ->values()
                    ->map(fn ($p) => [
                        'raw_score' => $p->raw_score,
                        'percentile_output' => $p->percentile_output,
                    ])
                    ->all(),
                'display_order' => $aptitudeArea->display_order,
                'is_active' => $aptitudeArea->is_active,
            ],
        ]);
    }

    public function update(UpdateAptitudeAreaRequest $request, AptitudeArea $aptitudeArea): RedirectResponse
    {
        $data = $request->validated();

        $aptitudeArea->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'max_items' => (int) $data['max_items'],
            'formula' => $data['formula'] ?? null,
            'scoring_method' => $data['scoring_method'] ?? 'formula',
            'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (($data['scoring_method'] ?? 'formula') === 'conversion_table') {
            $aptitudeArea->percentileConversions()->delete();
            if (! empty($data['conversion_table'])) {
                foreach ($data['conversion_table'] as $row) {
                    $aptitudeArea->percentileConversions()->create([
                        'raw_score' => $row['raw_score'],
                        'percentile_output' => $row['percentile_output'],
                    ]);
                }
            }
        } else {
            $aptitudeArea->percentileConversions()->delete();
        }

        app(AuditService::class)->log('aptitude_area.updated', AptitudeArea::class, $aptitudeArea->id, [], $data);

        return redirect()->route('admin.aptitude-areas.index')
            ->with('success', 'Aptitude area updated.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('reorder', AptitudeArea::class);

        $order = $request->input('order', []);

        if (! is_array($order)) {
            return redirect()->back()->with('error', 'Invalid order format');
        }

        foreach ($order as $index => $id) {
            AptitudeArea::where('id', $id)->update(['display_order' => $index]);
        }

        return redirect()->back()->with('success', 'Order saved successfully');
    }

    public function testFormula(Request $request): JsonResponse
    {
        $request->validate([
            'formula' => ['required', 'string'],
            'sample_raw_score' => ['required', 'numeric'],
            'max_items' => ['nullable', 'integer', 'min:1'],
        ]);

        $evaluator = app(FormulaEvaluator::class);

        if (! $evaluator->validate($request->formula)) {
            return response()->json(['error' => 'Invalid formula syntax'], 422);
        }

        $result = $evaluator->evaluate($request->formula, [
            'x' => (float) $request->sample_raw_score,
            'max_items' => (int) $request->input('max_items', 100),
        ]);

        if ($result === null) {
            return response()->json(['error' => 'Formula evaluation failed'], 422);
        }

        return response()->json(['result' => $result]);
    }

    public function destroy(AptitudeArea $aptitudeArea): RedirectResponse
    {
        $this->authorize('delete', $aptitudeArea);

        app(AuditService::class)->log('aptitude_area.deleted', AptitudeArea::class, $aptitudeArea->id, [], ['name' => $aptitudeArea->name]);

        $aptitudeArea->delete();

        return redirect()->route('admin.aptitude-areas.index')
            ->with('success', 'Aptitude area deleted.');
    }
}
