<?php

namespace App\ResultSheet\Support;

use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use Illuminate\Support\Str;

/**
 * Analyzes a result-sheet template (HTML content or uploaded Word/ODT
 * document) against the placeholder catalog: found / missing (required +
 * recommended) / extra. Required missing placeholders gate template saves
 * unless staff explicitly confirm (legacy parity).
 *
 * @phpstan-type AnalysisResult array{valid: bool, found: list<string>, missing: list<string>, extra: list<string>, recommended_missing: list<string>}
 */
final class ResultSheetTemplateAnalyzer
{
    public function __construct(private readonly DocxTextExtractor $extractor) {}

    /**
     * @return AnalysisResult
     */
    public function analyze(ResultSheetTemplate|array $source): array
    {
        $found = $this->extractPlaceholders($source);

        $catalog = $this->catalogKeys();

        $missing = array_values(array_diff($catalog['required'], $found));
        $recommendedMissing = array_values(array_diff($catalog['recommended'], $found));
        $extra = array_values(array_diff($found, $catalog['all']));

        return [
            'valid' => $missing === [],
            'found' => array_values($found),
            'missing' => $missing,
            'recommended_missing' => $recommendedMissing,
            'extra' => $extra,
        ];
    }

    /**
     * @param  ResultSheetTemplate|array<string, mixed>  $source
     * @return list<string>
     */
    private function extractPlaceholders(ResultSheetTemplate|array $source): array
    {
        if ($source instanceof ResultSheetTemplate) {
            if ($source->mode === ResultSheetTemplate::MODE_HTML) {
                return $this->fromContent((string) $source->content);
            }

            if ($source->document_path !== null) {
                return $this->fromDocument(storage_path('app/private/'.$source->document_path));
            }

            return [];
        }

        if (isset($source['content']) && is_string($source['content'])) {
            return $this->fromContent($source['content']);
        }

        if (isset($source['document_path']) && is_string($source['document_path']) && is_file($source['document_path'])) {
            return $this->fromDocument($source['document_path']);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function fromContent(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private function fromDocument(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'odt') {
            $text = $this->odtText($path);

            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);

            return array_values(array_unique($matches[1] ?? []));
        }

        return $this->extractor->placeholders($path);
    }

    private function odtText(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        try {
            $content = $zip->getFromName('content.xml') ?? '';
        } finally {
            $zip->close();
        }

        return strip_tags($content);
    }

    /**
     * @return array{required: list<string>, recommended: list<string>, all: list<string>}
     */
    public function catalogKeys(): array
    {
        $required = TemplateFieldAliasCatalog::required();
        $recommended = TemplateFieldAliasCatalog::recommended();

        $all = TemplateFieldAliasCatalog::staticKeys();

        // Per-domain placeholders for every active aptitude area.
        foreach (AptitudeArea::query()->where('is_active', true)->get(['code', 'name']) as $area) {
            $slug = $this->areaSlug($area->name);
            foreach (['', '_rating', '_raw', '_pct'] as $suffix) {
                $all[] = $slug.$suffix;
                $all[] = $slug.$suffix.'_2';
            }
        }

        // Legacy domain aliases.
        foreach (TemplateFieldAliasCatalog::domainAliases() as $alias => $code) {
            $all[] = $alias;
            $all[] = $alias.'_rating';
            $all[] = $alias.'_2';
            $all[] = $alias.'_rating_2';
        }

        // Course checkmarks for active programs.
        foreach (Program::query()->where('is_active', true)->get(['code']) as $program) {
            $all[] = Str::slug($program->code, '_').'_check';
            $all[] = Str::slug($program->code, '_').'_check_2';
        }

        // Dual-slot variants of every applicant field.
        foreach ($all as $key) {
            $all[] = $key.'_2';
        }

        return [
            'required' => $required,
            'recommended' => $recommended,
            'all' => array_values(array_unique($all)),
        ];
    }

    public function areaSlug(string $name): string
    {
        return str_replace('-', '_', Str::slug($name, '_'));
    }
}
