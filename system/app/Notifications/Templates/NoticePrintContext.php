<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\DocumentTemplate\Import\DocxPackageInspectionResult;
use App\DocumentTemplate\Import\DocxPackageInspector;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Support\MacroScanner;
use App\Models\Foundation\AptitudeArea;
use App\Models\Notifications\CommunicationTemplate;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Surfaces\ResultSheetSurface;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

final class NoticePrintContext
{
    public function __construct(private readonly DocxPackageInspector $inspector) {}

    public static function isOfficialScorecard(CommunicationTemplate $template): bool
    {
        return [$template->domain, $template->slug, $template->event_key]
            === ['results', 'released-online', 'result.released.online.v1'];
    }

    /** @return array{required:array,recommended:array,all:array,aliases:array} */
    public static function catalog(CommunicationTemplate $template): array
    {
        if (! self::isOfficialScorecard($template)) {
            $required = match ([$template->domain, $template->slug, $template->event_key]) {
                ['admissions', 'accepted', 'admission.accepted.v1'],
                ['examination', 'session-published', 'exam.session_published.v1'] => ['application_no'],
                default => [],
            };

            return ['required' => $required, 'recommended' => [], 'all' => CommunicationTemplateState::tokens($template), 'aliases' => []];
        }
        $catalog = app(ResultSheetSurface::class)->placeholderCatalog();
        $domains = array_keys(TemplateFieldAliasCatalog::domainAliases());
        foreach (AptitudeArea::query()->where('is_active', true)->pluck('name') as $name) {
            $domains[] = str_replace('-', '_', Str::slug($name, '_'));
        }
        foreach ($domains as $key) {
            foreach (['', '_rating', '_raw', '_pct'] as $suffix) {
                $catalog['all'][] = $key.$suffix;
            }
        }
        foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
            foreach (['_rating', '_raw', '_pct'] as $suffix) {
                if (in_array($canonical.$suffix, $catalog['all'], true)) {
                    $catalog['all'][] = $alias.$suffix;
                }
            }
        }
        $catalog['all'] = array_values(array_unique($catalog['all']));

        return $catalog;
    }

    /** @return array<string,list<array{placeholder:string,description:string}>> */
    public static function placeholderGroups(CommunicationTemplate $template): array
    {
        $descriptions = TemplateFieldAliasCatalog::descriptions();

        return ['print' => array_map(static fn (string $key): array => [
            'placeholder' => '{{'.$key.'}}', 'description' => $descriptions[$key] ?? 'Official print presentation field',
        ], self::catalog($template)['all'])];
    }

    /** @return array<string,mixed> */
    public static function sampleContext(CommunicationTemplate $template): array
    {
        return self::isOfficialScorecard($template)
            ? app(ResultSheetSurface::class)->sampleContext()
            : CommunicationTemplateRegistry::sampleContextFor($template->domain, $template->slug);
    }

    /** @return array{valid:bool,found:array,missing_required:array,unknown:array,unresolved:array,missing_recommended:array,has_printable_content:bool} */
    public function analyzeDocx(CommunicationTemplate $template, string $privateVerifiedPath, ?array $frozenCatalog = null): array
    {
        $package = $this->inspector->inspect($privateVerifiedPath);
        $document = $this->inspector->parseXml('word/document.xml', $package->documentXml);
        $documentXPath = new DOMXPath($document);
        $documentXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $documentXPath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $activeParts = $this->activeHeaderFooterParts($documentXPath, $package);
        $found = [];
        $unresolved = [];
        $hasPrintableContent = false;
        foreach ($package->allXmlParts as $part => $xml) {
            if (! str_starts_with($part, 'word/') || ! str_ends_with($part, '.xml')) {
                continue;
            }
            $dom = $this->inspector->parseXml($part, $xml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $headerFooter = preg_match('#^word/(?:header|footer)\\d*\\.xml$#D', $part) === 1;
            $supported = $part === 'word/document.xml' || ($headerFooter && isset($activeParts[$part]));
            foreach ($xpath->query('//w:p') as $paragraph) {
                $text = '';
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $leaf) {
                    if ($leaf instanceof DOMElement) {
                        $text .= match ($leaf->localName) {
                            'tab' => '\\t', 'br', 'cr' => '\\n', default => $leaf->textContent,
                        };
                    }
                }
                $scan = MacroScanner::scan($text);
                $visibleText = preg_replace('/\\{\\{\\s*[a-zA-Z0-9_.]+\\s*\\}\\}/u', '', $text) ?? $text;
                if ($supported && ($scan['found'] !== [] || preg_match('/[^\\s\\p{Z}]/u', $visibleText) === 1)) {
                    $hasPrintableContent = true;
                }
                if (! $supported && ($scan['found'] !== [] || $scan['unresolved'] !== [])) {
                    $unresolved['unsupported_part:'.$part] = 'unsupported_part:'.$part;
                }
                foreach ($scan['found'] as $key) {
                    $found[$key] = $key;
                }
                foreach ($scan['unresolved'] as $fragment) {
                    $unresolved[$fragment] = $fragment;
                }
                $this->inspector->assertWithinDeadline();
            }
            foreach ($xpath->query('//w:instrText | //w:delText') as $field) {
                $scan = MacroScanner::scan($field->textContent);
                if ($scan['found'] !== [] || $scan['unresolved'] !== []) {
                    $unresolved['unsupported_field:'.$part] = 'unsupported_field:'.$part;
                }
                $this->inspector->assertWithinDeadline();
            }
            if ($supported && $this->hasReferencedRasterImage($xpath, $package, $part)) {
                $hasPrintableContent = true;
            }
        }
        $found = array_values($found);
        $unresolved = array_values($unresolved);
        $catalog = $frozenCatalog ?? self::catalog($template);
        $canonical = array_map(TemplateFieldAliasCatalog::canonical(...), $found);
        $unknown = array_values(array_diff($found, $catalog['all']));
        $missing = array_values(array_diff($catalog['required'], $canonical));

        return ['valid' => $unknown === [] && $unresolved === [] && $missing === [], 'found' => $found,
            'missing_required' => $missing, 'unknown' => $unknown, 'unresolved' => $unresolved,
            'missing_recommended' => array_values(array_diff($catalog['recommended'], $canonical)),
            'has_printable_content' => $hasPrintableContent];
    }

    /** @param array<string, mixed> $blueprint @return array{bindings:list<string>,unresolved:list<string>,has_printable_content:bool} */
    public static function analyzeBuilder(array $blueprint): array
    {
        $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());
        $html = $compiler->compile($blueprint);
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $text = '';
        foreach ($xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
            $text .= $node->textContent;
        }
        $scan = MacroScanner::scan($text);
        $withoutTokens = preg_replace('/\\{\\{\\s*[a-zA-Z0-9_.]+\\s*\\}\\}/u', '', $text) ?? $text;
        $hasPrintableContent = preg_match('/<img\\b/i', $html) === 1
            || preg_match('/[^\\s\\p{Z}]/u', $withoutTokens) === 1
            || $scan['found'] !== [];

        return ['bindings' => $scan['found'], 'unresolved' => $scan['unresolved'],
            'has_printable_content' => $hasPrintableContent];
    }

    /** @param array<string, mixed> $blueprint */
    public static function hasPrintableBuilderContent(array $blueprint): bool
    {
        return self::analyzeBuilder($blueprint)['has_printable_content'];
    }

    /** @param list<string> $bindings @return list<string> */
    public static function missingRequiredBindings(CommunicationTemplate $template, array $bindings): array
    {
        $catalog = self::catalog($template);
        $canonicalBindings = array_map(TemplateFieldAliasCatalog::canonical(...), $bindings);

        return array_values(array_diff($catalog['required'], $canonicalBindings));
    }

    /** @return array<string, true> */
    private function activeHeaderFooterParts(DOMXPath $documentXPath, DocxPackageInspectionResult $package): array
    {
        $relationships = [];
        foreach ($package->relationships['word/_rels/document.xml.rels'] ?? [] as $relationship) {
            $relationships[$relationship['id']] = $relationship;
        }
        $activeParts = ['word/document.xml' => true];
        $references = $documentXPath->query(
            '//w:sectPr[not(ancestor::w:sectPrChange)]/w:headerReference/@r:id'
            .' | //w:sectPr[not(ancestor::w:sectPrChange)]/w:footerReference/@r:id'
        );
        foreach ($references ?: [] as $reference) {
            if (! $reference instanceof \DOMAttr || ! $reference->ownerElement instanceof DOMElement) {
                throw new DocxPackageInspectionException('DOCX section contains an invalid header or footer reference.');
            }
            $kind = $reference->ownerElement->localName === 'headerReference' ? 'header' : 'footer';
            $relationship = $relationships[$reference->value] ?? null;
            if (! is_array($relationship)
                || $relationship['target_mode'] === 'External'
                || $relationship['type'] !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$kind
                || ! preg_match('#^word/'.$kind.'\\d*\\.xml$#D', $relationship['target'])
                || ! isset($package->allXmlParts[$relationship['target']])) {
                throw new DocxPackageInspectionException('DOCX section references an unavailable header or footer.');
            }
            $activeParts[$relationship['target']] = true;
        }

        return $activeParts;
    }

    private function hasReferencedRasterImage(DOMXPath $xpath, DocxPackageInspectionResult $package, string $part): bool
    {
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipPart = dirname($part).'/_rels/'.basename($part).'.rels';
        $targets = [];
        foreach ($package->relationships[$relationshipPart] ?? [] as $relationship) {
            if ($relationship['target_mode'] === null || $relationship['target_mode'] === 'Internal') {
                $targets[$relationship['id']] = $relationship['target'];
            }
        }
        foreach ($xpath->query('//w:drawing//a:blip/@r:embed') as $reference) {
            $target = $targets[$reference->nodeValue] ?? null;
            if (is_string($target) && isset($package->images[$target])) {
                return true;
            }
        }

        return false;
    }
}
