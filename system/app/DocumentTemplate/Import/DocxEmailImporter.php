<?php

declare(strict_types=1);

namespace App\DocumentTemplate\Import;

use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocxEmailImporter
{
    public const CONVERTER_VERSION = 'docx-email-importer-v2';

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function __construct(private readonly DocxPackageInspector $packageInspector = new DocxPackageInspector) {}

    /**
     * Image storage is a caller-owned private association, not a ZIP-path or checksum identifier.
     *
     * @param  null|callable(array<string,mixed>):string  $storeImage
     * @param  null|list<string>  $allowedTokens
     * @param  list<string>  $requiredTokens
     */
    public function import(string|UploadedFile $file, ?DocxPackageInspectionResult $inspection = null, ?callable $storeImage = null, ?array $allowedTokens = null, array $requiredTokens = []): DocxEmailImportResult
    {
        $this->packageInspector->startProcessing();
        if ($inspection !== null) {
            $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            if (! is_string($path) || ! is_file($path) || filesize($path) > DocxPackageInspector::MAX_FILE_BYTES
                || ! hash_equals($inspection->sha256, (string) hash_file('sha256', $path))) {
                throw new DocxPackageInspectionException('Source checksum changed after package inspection.');
            }
        }
        $inspection ??= $this->packageInspector->inspect($file);
        $dom = $this->packageInspector->parseXml('word/document.xml', $inspection->documentXml);
        $xpath = $this->xpath($dom);
        $findings = [];
        self::assertTokens($this->tokenText($xpath), $allowedTokens);
        $this->collectUnsupported($inspection, $findings, $allowedTokens);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new DocxPackageInspectionException('The Word document has no supported body.');
        }
        $numbering = $this->numbering($inspection);
        $paragraphStyles = $this->paragraphStyles($inspection);
        $blocks = [];
        $mapped = [];
        $listKey = null;
        $listStack = [];
        $listCounts = [];
        $imageIds = [];
        foreach ($body->childNodes as $node) {
            $this->packageInspector->assertWithinDeadline();
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->namespaceURI !== self::W) {
                $this->finding($findings, 'word/document.xml', $node, 'unsupported_body', 'requires_correction');

                continue;
            }
            if ($node->localName === 'tbl') {
                $listKey = null;
                $table = $this->table($xpath, $node, $inspection, $findings);
                if ($table !== null) {
                    $blocks[] = $table;
                    $mapped[] = ['source_part' => 'word/document.xml', 'source_location' => $node->getNodePath(), 'kind' => 'table'];
                }

                continue;
            }
            if ($node->localName !== 'p') {
                if ($node->localName !== 'sectPr') {
                    $this->finding($findings, 'word/document.xml', $node, 'unsupported_body', 'requires_correction');
                }

                continue;
            }
            $runs = $this->runs($xpath, $node, $inspection->documentRels);
            $style = $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $node);
            $numId = $xpath->evaluate('string(./w:pPr/w:numPr/w:numId/@w:val)', $node);
            $level = (int) $xpath->evaluate('string(./w:pPr/w:numPr/w:ilvl/@w:val)', $node);
            if ($numId === '') {
                $numId = $paragraphStyles[$style]['num_id'] ?? '';
                $level = $paragraphStyles[$style]['list_level'] ?? 0;
            }
            if ($numId === '0') {
                $numId = '';
            }
            $definition = $numbering[$numId][$level] ?? null;
            if ($level < 0 || $level >= NoticeEmailSchema::MAX_DEPTH) {
                $definition = null;
            }
            $images = $xpath->query('.//wp:inline', $node);
            $mappedKind = 'paragraph';
            if ($numId !== '' && $definition === null) {
                $this->finding($findings, 'word/document.xml', $node, 'unresolved_numbering', 'requires_correction');
            }
            if ($definition !== null && $images->length > 0) {
                $this->finding($findings, 'word/document.xml', $node, 'list_image_flow', 'requires_correction');
            }
            if ($definition !== null && $images->length === 0) {
                $mappedKind = 'list_item';
                if ($listKey !== $numId || $level === 0 && ($listStack[0]['ordered'] ?? null) !== ($definition === 'decimal')) {
                    if ($level !== 0 || ($definition === 'decimal' && isset($listCounts[$numId]))) {
                        $this->finding($findings, 'word/document.xml', $node, 'list_restart_or_level_jump', 'requires_correction');
                    }
                    $blocks[] = ['type' => 'list', 'ordered' => $definition === 'decimal', 'items' => []];
                    $listStack = [];
                    $listStack[0] = &$blocks[array_key_last($blocks)];
                    $listKey = $numId;
                }
                if ($level > 0) {
                    if (! isset($listStack[$level - 1])) {
                        $this->finding($findings, 'word/document.xml', $node, 'list_level_jump', 'requires_correction');
                        $level = 0;
                    } elseif (! isset($listStack[$level]) || $listStack[$level]['ordered'] !== ($definition === 'decimal')) {
                        $parent = &$listStack[$level - 1];
                        $parent['items'][] = ['type' => 'list', 'ordered' => $definition === 'decimal', 'items' => []];
                        $listStack[$level] = &$parent['items'][array_key_last($parent['items'])];
                        unset($parent);
                    }
                }
                foreach (array_keys($listStack) as $depth) {
                    if ($depth > $level) {
                        unset($listStack[$depth]);
                    }
                }
                $listStack[$level]['items'][] = ['runs' => $runs === [] ? [['text' => '']] : $runs];
                $listCounts[$numId] = ($listCounts[$numId] ?? 0) + 1;
            } else {
                $listKey = null;
                $outline = $xpath->evaluate('string(./w:pPr/w:outlineLvl/@w:val)', $node);
                $heading = preg_match('/^heading\s*([1-6])$/i', $style, $match) ? (int) $match[1] : (ctype_digit($outline) && (int) $outline <= 5 ? (int) $outline + 1 : ($paragraphStyles[$style]['heading'] ?? null));
                $align = $xpath->evaluate('string(./w:pPr/w:jc/@w:val)', $node);
                $base = ['type' => $heading === null ? 'paragraph' : 'heading'];
                if ($heading !== null) {
                    $mappedKind = 'heading';
                    $base['level'] = $heading;
                }
                if (in_array($align, ['left', 'right', 'center', 'both'], true)) {
                    $base['align'] = $align === 'both' ? 'justify' : $align;
                }
                // Split paragraph at each inline image to retain text/image/text reading order.
                $pending = [];
                foreach ($this->paragraphSegments($xpath, $node, $inspection->documentRels) as $segment) {
                    if ($segment instanceof DOMElement) {
                        if ($pending !== []) {
                            $blocks[] = $base + ['runs' => $this->tokens($pending)];
                            $pending = [];
                        }
                        $blocks[] = $this->image($xpath, $segment, $inspection, $storeImage, $imageIds);
                        $mapped[] = ['source_part' => 'word/document.xml', 'source_location' => $segment->getNodePath(), 'kind' => 'image'];
                        $this->finding($findings, 'word/document.xml', $segment, 'image_alt_review', 'acknowledgment');
                    } else {
                        $pending[] = $segment;
                    }
                }
                if ($pending !== [] || $images->length === 0) {
                    $blocks[] = $base + ['runs' => $pending === [] ? [['text' => '']] : $this->tokens($pending)];
                }
            }
            $mapped[] = ['source_part' => 'word/document.xml', 'source_location' => $node->getNodePath(), 'kind' => $mappedKind];
        }
        $blueprint = ['schema_version' => NoticeEmailSchema::SCHEMA_VERSION, 'layout' => 'flow', 'blocks' => $blocks];
        NoticeEmailSchema::assertValid($blueprint, $allowedTokens);
        $text = self::authoredText($blueprint);
        self::assertTokens($text, $allowedTokens, $requiredTokens);
        $report = ['source_sha256' => $inspection->sha256, 'converter_version' => self::CONVERTER_VERSION, 'mapped_blocks_count' => count($blocks), 'mapped' => $mapped, 'findings' => $findings];
        $json = self::reportJson($report);
        $report['report_sha256'] = hash('sha256', $json);

        return new DocxEmailImportResult($blueprint, $report, $inspection->images, $inspection);
    }

    public static function reportJson(array $report): string
    {
        unset($report['report_id'], $report['report_sha256']);
        $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > NoticeEmailSchema::MAX_JSON_BYTES) {
            throw new DocxPackageInspectionException('Import report exceeds the bounded report size.');
        }

        return $json;
    }

    public static function authoredText(array $value): string
    {
        $hasToken = isset($value['token']) && is_string($value['token']);
        $text = $hasToken ? '{{'.$value['token'].'}}' : '';
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $text .= self::authoredText($item);
            } elseif (is_string($item) && (is_int($key) || in_array($key, ['text', 'alt', 'href', 'link'], true))
                && ! ($hasToken && $key === 'text')) {
                $text .= $item;
            }
        }

        return $text;
    }

    /** @param null|list<string> $allowed @param list<string> $required */
    public static function assertTokens(string $text, ?array $allowed, array $required = []): void
    {
        preg_match_all('/\{\{\s*([a-zA-Z][a-zA-Z0-9_.]*)\s*\}\}/u', $text, $matches);
        $rest = preg_replace('/\{\{\s*([a-zA-Z][a-zA-Z0-9_.]*)\s*\}\}/u', '', $text);
        if (str_contains($rest, '{{') || str_contains($rest, '}}')) {
            throw new DocxPackageInspectionException('Malformed notice token cannot be acknowledged.');
        }
        if ($allowed !== null && array_diff($matches[1], $allowed) !== []) {
            throw new DocxPackageInspectionException('Unknown notice token cannot be acknowledged.');
        }
        if (array_diff($required, $matches[1]) !== []) {
            throw new DocxPackageInspectionException('Missing required notice token cannot be acknowledged.');
        }
    }

    private function xpath(\DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        foreach (['w' => self::W, 'r' => self::R, 'wp' => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main', 'v' => 'urn:schemas-microsoft-com:vml', 'm' => 'http://schemas.openxmlformats.org/officeDocument/2006/math'] as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    private function collectUnsupported(DocxPackageInspectionResult $inspection, array &$findings, ?array $allowedTokens): void
    {
        foreach ($inspection->allXmlParts as $part => $xml) {
            $xpath = $this->xpath($this->packageInspector->parseXml($part, $xml));
            self::assertTokens($this->tokenText($xpath), $allowedTokens);
            if (preg_match('~^word/(?:header|footer|footnotes|endnotes|charts/|diagrams/)~', $part)) {
                $this->finding($findings, $part, $xpath->document->documentElement, 'omitted_part', 'requires_correction');

                continue;
            }
            foreach ($xpath->query('//wp:anchor | //wp:inline[not(.//a:blip/@r:embed)] | //w:pict | //w:txbxContent | //w:sdt | //w:customXml | //w:smartTag | //m:oMath | //w:fldSimple | //w:fldChar | //w:instrText | //w:ins | //w:del | //w:rPrChange | //w:pPrChange | //w:footnoteReference | //w:endnoteReference | //a:graphicData[not(contains(@uri,"picture"))] | //w:r/*[not(self::w:rPr or self::w:t or self::w:br or self::w:cr or self::w:tab or self::w:drawing)]') as $node) {
                $disposition = in_array($node->localName, ['fldChar', 'instrText'], true) ? 'acknowledgment' : 'requires_correction';
                $this->finding($findings, $part, $node, 'unsupported_'.$node->localName, $disposition);
            }
            foreach ($xpath->query('//w:br[@w:type="page"] | //w:lastRenderedPageBreak | //w:pageBreakBefore | //w:pgSz | //w:pgMar') as $node) {
                $this->finding($findings, $part, $node, 'page_control', 'acknowledgment');
            }
            foreach ($part === 'word/document.xml' ? $xpath->query('//w:rFonts | //w:sz | //w:spacing | //w:ind | //w:shd | //w:pBdr | //w:highlight | //w:vertAlign | //w:tab | //w:tabs | //w:tblBorders | //w:tcBorders | //w:tblW | //w:tcW | //w:pStyle', $xpath->document->documentElement) : [] as $node) {
                $this->finding($findings, $part, $node, 'normalized_style', 'acknowledgment');
            }
            if ($part === 'word/document.xml') {
                foreach ($xpath->query('//w:hyperlink') as $link) {
                    $target = $inspection->documentRels[$link->getAttributeNS(self::R, 'id')] ?? '';
                    if (! NoticeEmailLinkPolicy::allows($target)) {
                        $this->finding($findings, $part, $link, 'hyperlink_inert', 'acknowledgment');
                    }
                }
            }
        }
    }

    private function tokenText(DOMXPath $xpath): string
    {
        $text = '';
        $previousParagraph = null;
        foreach ($xpath->query('//text()[normalize-space(.) != "" or local-name(..)="t" or local-name(..)="instrText" or local-name(..)="delText" or local-name(..)="v"]') as $node) {
            $this->packageInspector->assertWithinDeadline();
            $paragraph = $node->parentNode;
            while ($paragraph !== null && $paragraph->localName !== 'p') {
                $paragraph = $paragraph->parentNode;
            }
            if ($text !== '' && (($paragraph === null) !== ($previousParagraph === null)
                || $paragraph !== null && $previousParagraph !== null && ! $paragraph->isSameNode($previousParagraph))) {
                $text .= "\n";
            }
            $previousParagraph = $paragraph;
            $text .= $node->nodeValue;
        }

        return $text;
    }

    private function finding(array &$findings, string $part, DOMNode $node, string $kind, string $disposition): void
    {
        if (count($findings) >= 1000) {
            throw new DocxPackageInspectionException('Import report finding limit exceeded.');
        }
        $location = $node->getNodePath();
        $text = $this->sourceText($node);
        $findings[] = ['id' => hash('sha256', $part.'|'.$location.'|'.$kind), 'source_part' => $part, 'source_location' => $location, 'kind' => $kind, 'severity' => $disposition === 'requires_correction' ? 'critical' : 'info', 'disposition' => $disposition, 'source_text' => $text];
    }

    private function sourceText(DOMNode $node): string
    {
        if (in_array($node->localName, ['fldChar', 'instrText'], true)) {
            return '';
        }
        $xpath = new DOMXPath($node->ownerDocument);
        $visible = $xpath->query('.//*[local-name()="t" or local-name()="delText" or local-name()="v"]', $node);
        if ($visible->length === 0) {
            return trim($node->textContent);
        }
        $text = '';
        foreach ($visible as $part) {
            $text .= $part->textContent;
        }

        return trim($text);
    }

    private function numbering(DocxPackageInspectionResult $inspection): array
    {
        if (! isset($inspection->allXmlParts['word/numbering.xml'])) {
            return [];
        }
        $xpath = $this->xpath($this->packageInspector->parseXml('word/numbering.xml', $inspection->allXmlParts['word/numbering.xml']));
        $abstract = [];
        foreach ($xpath->query('//w:abstractNum/w:lvl') as $level) {
            $format = $xpath->evaluate('string(w:numFmt/@w:val)', $level);
            $start = $xpath->evaluate('string(w:start/@w:val)', $level);
            $label = $xpath->evaluate('string(w:lvlText/@w:val)', $level);
            if ($format === 'decimal' && $label !== '' && ! preg_match('/^%[1-9][.)]?$/', $label)) {
                continue;
            }
            if (in_array($format, ['bullet', 'decimal'], true) && ($format === 'bullet' || $start === '' || $start === '1')) {
                $abstract[$level->parentNode->getAttributeNS(self::W, 'abstractNumId')][$level->getAttributeNS(self::W, 'ilvl')] = $format;
            }
        }
        $result = [];
        foreach ($xpath->query('//w:num') as $num) {
            if ($xpath->query('w:lvlOverride', $num)->length === 0) {
                $result[$num->getAttributeNS(self::W, 'numId')] = $abstract[$xpath->evaluate('string(w:abstractNumId/@w:val)', $num)] ?? [];
            }
        }

        return $result;
    }

    private function paragraphStyles(DocxPackageInspectionResult $inspection): array
    {
        if (! isset($inspection->allXmlParts['word/styles.xml'])) {
            return [];
        }
        $xpath = $this->xpath($this->packageInspector->parseXml('word/styles.xml', $inspection->allXmlParts['word/styles.xml']));
        $styles = [];
        foreach ($xpath->query('//w:style[@w:type="paragraph"]') as $style) {
            $id = $style->getAttributeNS(self::W, 'styleId');
            $outline = $xpath->evaluate('string(./w:pPr/w:outlineLvl/@w:val)', $style);
            $name = $xpath->evaluate('string(./w:name/@w:val)', $style);
            $heading = ctype_digit($outline) && (int) $outline <= 5 ? (int) $outline + 1 : (preg_match('/^heading\s*([1-6])$/i', $name, $match) ? (int) $match[1] : null);
            $styles[$id] = ['heading' => $heading, 'parent' => $xpath->evaluate('string(./w:basedOn/@w:val)', $style),
                'num_id' => $xpath->evaluate('string(./w:pPr/w:numPr/w:numId/@w:val)', $style),
                'list_level' => (int) $xpath->evaluate('string(./w:pPr/w:numPr/w:ilvl/@w:val)', $style)];
        }
        $resolved = [];
        foreach ($styles as $id => $style) {
            $resolved[$id] = ['heading' => null, 'num_id' => '', 'list_level' => 0];
            for ($depth = 0; $depth < NoticeEmailSchema::MAX_DEPTH; $depth++) {
                $resolved[$id]['heading'] ??= $style['heading'];
                if ($resolved[$id]['num_id'] === '' && $style['num_id'] !== '') {
                    $resolved[$id]['num_id'] = $style['num_id'];
                    $resolved[$id]['list_level'] = $style['list_level'];
                }
                if (! isset($styles[$style['parent']])) {
                    break;
                }
                $style = $styles[$style['parent']];
            }
        }

        return $resolved;
    }

    private function paragraphSegments(DOMXPath $xpath, DOMElement $p, array $relationships): array
    {
        $segments = [];
        foreach ($xpath->query('./w:r | ./w:hyperlink/w:r', $p) as $r) {
            $style = [];
            foreach (['b' => 'bold', 'i' => 'italic', 'u' => 'underline'] as $word => $key) {
                $property = $xpath->query('./w:rPr/w:'.$word, $r)->item(0);
                if ($property instanceof DOMElement && ! in_array($property->getAttributeNS(self::W, 'val'), ['0', 'false', 'none'], true)) {
                    $style[$key] = true;
                }
            }
            $color = $xpath->evaluate('string(./w:rPr/w:color/@w:val)', $r);
            if (preg_match('/^[a-f0-9]{6}$/i', $color)) {
                $style['color'] = '#'.$color;
            }
            if ($r->parentNode->localName === 'hyperlink') {
                $link = $relationships[$r->parentNode->getAttributeNS(self::R, 'id')] ?? null;
                if (is_string($link) && NoticeEmailLinkPolicy::allows($link)) {
                    $style['link'] = $link;
                }
            }
            foreach ($r->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                if ($child->localName === 'drawing') {
                    foreach ($xpath->query('./wp:inline[.//a:blip/@r:embed]', $child) as $drawing) {
                        $segments[] = $drawing;
                    }
                } elseif ($child->namespaceURI === self::W && in_array($child->localName, ['t', 'tab', 'br', 'cr'], true)) {
                    if ($child->localName === 'br' && $child->getAttributeNS(self::W, 'type') === 'page') {
                        continue;
                    }
                    $text = match ($child->localName) {
                        'tab' => "\t", 'br', 'cr' => "\n", default => $child->textContent
                    };
                    $last = array_key_last($segments);
                    if ($last !== null && is_array($segments[$last]) && array_diff_assoc($segments[$last], ['text' => $segments[$last]['text']] + $style) === [] && count($segments[$last]) === count($style) + 1) {
                        $segments[$last]['text'] .= $text;
                    } else {
                        $segments[] = ['text' => $text] + $style;
                    }
                }
            }
        }

        return $segments;
    }

    private function runs(DOMXPath $xpath, DOMElement $p, array $relationships): array
    {
        return $this->tokens(array_values(array_filter($this->paragraphSegments($xpath, $p, $relationships), 'is_array')));
    }

    private function tokens(array $runs): array
    {
        $text = implode('', array_column($runs, 'text'));
        preg_match_all('/\{\{\s*[a-zA-Z][a-zA-Z0-9_.]*\s*\}\}/u', $text, $matches, PREG_OFFSET_CAPTURE);
        $spans = [];
        $offset = 0;
        foreach ($runs as $run) {
            $length = strlen($run['text']);
            $spans[] = [$offset, $offset + $length, $run];
            $offset += $length;
        }
        $result = [];
        $cursor = 0;
        foreach ($matches[0] as [$token, $start]) {
            $this->sliceRuns($result, $spans, $cursor, $start);
            foreach ($spans as [$from, $to, $run]) {
                if ($start >= $from && $start < $to) {
                    $run['text'] = $token;
                    $result[] = $run;
                    break;
                }
            }
            $cursor = $start + strlen($token);
        }
        $this->sliceRuns($result, $spans, $cursor, strlen($text));

        return $result;
    }

    private function sliceRuns(array &$result, array $spans, int $start, int $end): void
    {
        foreach ($spans as [$from, $to, $run]) {
            $left = max($from, $start);
            $right = min($to, $end);
            if ($right > $left) {
                $run['text'] = substr($run['text'], $left - $from, $right - $left);
                $result[] = $run;
            }
        }
    }

    private function image(DOMXPath $xpath, DOMElement $node, DocxPackageInspectionResult $inspection, ?callable $storeImage, array &$ids): array
    {
        $rid = $xpath->evaluate('string(.//a:blip/@r:embed)', $node);
        $target = $inspection->documentRels[$rid] ?? '';
        $relationship = null;
        foreach ($inspection->relationships['word/_rels/document.xml.rels'] ?? [] as $entry) {
            if ($entry['id'] === $rid) {
                $relationship = $entry;
                break;
            }
        }
        if ($relationship === null || $relationship['type'] !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'
            || $relationship['target_mode'] === 'External') {
            throw new DocxPackageInspectionException('Inline image requires a local raster image relationship.');
        }
        $image = $inspection->images[$target] ?? null;
        if ($image === null || $storeImage === null) {
            throw new DocxPackageInspectionException('Inline image requires a verified private owned asset association.');
        }
        $ids[$target] ??= $storeImage($image);
        if (! Str::isUuid($ids[$target])) {
            throw new DocxPackageInspectionException('Image storage must return an opaque private asset identity.');
        }
        $alt = $xpath->evaluate('string(./wp:docPr/@descr)', $node);
        $cx = (int) $xpath->evaluate('string(./wp:extent/@cx)', $node);
        $width = $cx > 0 ? max(1, (int) round($cx / 9525)) : $image['width'];
        $cy = (int) $xpath->evaluate('string(./wp:extent/@cy)', $node);
        $height = $cy > 0 ? max(1, (int) round($cy / 9525)) : $image['height'];

        return ['type' => 'image', 'asset_id' => $ids[$target], 'width' => min(600, $width), 'height' => max(1, (int) round($height * min(600, $width) / $width)), 'alt' => $alt];
    }

    private function table(DOMXPath $xpath, DOMElement $table, DocxPackageInspectionResult $inspection, array &$findings): ?array
    {
        if ($xpath->query('.//w:tbl | .//w:gridSpan | .//w:vMerge | .//w:hMerge | ./w:tblPr/w:tblpPr | ./w:tblPr/w:tblLayout[@w:type="fixed"] | .//wp:inline', $table)->length > 0) {
            $this->finding($findings, 'word/document.xml', $table, 'unsupported_table', 'requires_correction');

            return null;
        }
        $rows = [];
        $columns = null;
        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) as $cell) {
                $runs = [];
                foreach ($xpath->query('./w:p', $cell) as $p) {
                    if ($runs !== []) {
                        $runs[] = ['text' => "\n"];
                    }
                    array_push($runs, ...$this->runs($xpath, $p, $inspection->documentRels));
                }
                $cells[] = ['runs' => $runs === [] ? [['text' => '']] : $runs];
            }
            $columns ??= count($cells);
            if ($columns === 0 || count($cells) !== $columns) {
                $this->finding($findings, 'word/document.xml', $table, 'nonrectangular_table', 'requires_correction');

                return null;
            }
            $rows[] = $cells;
        }

        return $rows === [] ? null : ['type' => 'table', 'rows' => $rows];
    }
}
