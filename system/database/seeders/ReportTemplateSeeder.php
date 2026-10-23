<?php

namespace Database\Seeders;

use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\OfficeDocumentPackCatalog;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\Models\Reports\ReportTemplate;
use Illuminate\Database\Seeder;
use RuntimeException;

class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());

        foreach (DocumentSurfaceRegistry::definitions() as $surfaceKey => $definition) {
            if ($surfaceKey === 'custom_document') {
                continue;
            }

            $surface = DocumentSurfaceRegistry::resolve($surfaceKey);

            if ($surface === null) {
                throw new RuntimeException('Report surface '.$surfaceKey.' could not be resolved.');
            }

            $blueprint = $surface->defaultBlueprint('a4', 'portrait');
            $pack = OfficeDocumentPackCatalog::forSurface($surfaceKey);
            $selectedLeaves = OfficeDocumentPackCatalog::defaultSelectedLeavesForSurface($surfaceKey);
            $compiled = [
                'document' => $blueprint,
                'html' => $compiler->compile($blueprint),
            ];
            $activeTemplate = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            $defaultName = 'Standard '.$surface->title().' (System Default)';
            $existingDefault = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->where('name', $defaultName)
                ->exists();

            if (! $existingDefault) {
                ReportTemplate::query()->create([
                    'surface_key' => $surfaceKey,
                    'name' => $defaultName,
                    'mode' => ReportTemplate::MODE_HTML,
                    'source_kind' => ReportTemplate::SOURCE_BUILDER,
                    'paper_size' => 'a4',
                    'orientation' => 'portrait',
                    'grain' => $pack['grain'],
                    'selected_leaves' => $selectedLeaves,
                    'mounted_slots' => $pack['default_mounted_slots'],
                    'builder_schema_version' => 1,
                    'builder_json' => $compiled['document'],
                    'compiled_html' => $compiled['html'],
                    'content' => $compiled['html'],
                    'compiler_version' => 'document-blueprint-1',
                    'is_active' => $activeTemplate === null,
                ]);
            }
        }
    }
}
