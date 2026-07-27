<?php

namespace App\Services;

use App\ValueObjects\DocxValidationResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResultSheetOdtService
{
    public function renderFromStoragePath(?string $path, array $replacements): string
    {
        if (! $path) {
            return '<p class="text-muted-foreground">No document template.</p>';
        }

        return $this->renderFromFullPath(Storage::path($path), $replacements);
    }

    public function renderFromFullPath(string $fullPath, array $replacements): string
    {
        if (! is_file($fullPath)) {
            return '<p class="text-destructive">ODT file not found.</p>';
        }

        try {
            $repairedPath = $this->getRepairedTemplate($fullPath) ?: $fullPath;

            $zip = new \ZipArchive;
            if ($zip->open($repairedPath) !== true) {
                return '<p class="text-destructive">Failed to open ODT file.</p>';
            }

            $contentXml = $zip->getFromName('content.xml');
            $zip->close();

            if ($contentXml === false) {
                return '<p class="text-destructive">ODT file missing content.xml.</p>';
            }

            foreach ($replacements as $key => $value) {
                $contentXml = str_replace('{{'.$key.'}}', htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $contentXml);
            }

            $html = $this->convertOdtXmlToHtml($contentXml);

            if ($repairedPath !== $fullPath && is_file($repairedPath)) {
                @unlink($repairedPath);
            }

            return $html;
        } catch (\Throwable $e) {
            Log::error('ODT render failed', ['path' => $fullPath, 'error' => $e->getMessage()]);

            return '<p class="text-destructive">Unexpected error rendering ODT template.</p>';
        }
    }

    public function validateTemplate(string $fullPath, array $categorizedPlaceholders, bool $isCrosswise): DocxValidationResult
    {
        $requiredAndRecommended = array_merge($categorizedPlaceholders['required'], $categorizedPlaceholders['recommended']);
        $optionalAll = array_merge(
            $categorizedPlaceholders['optional'],
            $categorizedPlaceholders['html_only'] ?? [],
            $categorizedPlaceholders['domain'],
            $categorizedPlaceholders['personnel'],
            $categorizedPlaceholders['institution'],
        );

        if ($isCrosswise) {
            $optionalAll = array_merge($optionalAll, $categorizedPlaceholders['applicant2']);
        }

        $allKnown = array_merge($requiredAndRecommended, $optionalAll);

        $checks = [];

        if (! is_file($fullPath)) {
            $checks[] = ['label' => 'ODT file readable', 'detail' => 'File not found', 'status' => 'fail'];
            $checks[] = ['label' => 'Required placeholders', 'detail' => count($requiredAndRecommended).' expected', 'status' => 'fail'];
            $checks[] = ['label' => 'Optional placeholders', 'detail' => '0/'.count($optionalAll).' used', 'status' => 'warn'];

            return new DocxValidationResult(
                valid: false,
                found: [],
                missing: $requiredAndRecommended,
                missingOptional: $optionalAll,
                extra: [],
                checks: $checks,
            );
        }

        $checks[] = ['label' => 'ODT file readable', 'detail' => 'OK', 'status' => 'pass'];

        $zip = new \ZipArchive;
        if ($zip->open($fullPath) !== true) {
            $checks[] = ['label' => 'ODT file readable', 'detail' => 'Cannot open ZIP', 'status' => 'fail'];

            return new DocxValidationResult(
                valid: false,
                found: [],
                missing: $requiredAndRecommended,
                missingOptional: $optionalAll,
                extra: [],
                checks: $checks,
            );
        }

        $contentXml = $zip->getFromName('content.xml');
        $zip->close();

        if ($contentXml === false) {
            $checks[] = ['label' => 'ODT content.xml', 'detail' => 'Missing content.xml', 'status' => 'fail'];

            return new DocxValidationResult(
                valid: false,
                found: [],
                missing: $requiredAndRecommended,
                missingOptional: $optionalAll,
                extra: [],
                checks: $checks,
            );
        }

        $repairedXml = $this->repairOdtXml($contentXml);
        $odtPlaceholders = $this->extractOdtPlaceholders($repairedXml);

        $found = array_values(array_intersect($allKnown, $odtPlaceholders));
        $missing = array_values(array_diff($requiredAndRecommended, $odtPlaceholders));
        if (in_array('applicant_reference', $missing) && (in_array('applicant_number', $odtPlaceholders) || in_array('applicant_no', $odtPlaceholders))) {
            $missing = array_values(array_diff($missing, ['applicant_reference']));
        }
        if (in_array('course_applied', $missing) && in_array('course_applied_code', $odtPlaceholders)) {
            $missing = array_values(array_diff($missing, ['course_applied']));
        }
        $missingOptional = array_values(array_diff($optionalAll, $odtPlaceholders));
        $extra = array_values(array_diff($odtPlaceholders, $allKnown));

        $checks[] = empty($missing)
            ? ['label' => 'Required placeholders', 'detail' => count($requiredAndRecommended).' found', 'status' => 'pass']
            : ['label' => 'Required placeholders', 'detail' => count($missing).' missing', 'status' => 'fail'];

        $optUsed = count($optionalAll) - count($missingOptional);
        $checks[] = $optUsed > 0
            ? ['label' => 'Optional placeholders', 'detail' => $optUsed.'/'.count($optionalAll).' used', 'status' => 'pass']
            : ['label' => 'Optional placeholders', 'detail' => '0/'.count($optionalAll).' used', 'status' => 'warn'];

        $checks[] = empty($extra)
            ? ['label' => 'No unknown placeholders', 'detail' => 'Clean', 'status' => 'pass']
            : ['label' => 'Unknown placeholders', 'detail' => count($extra).' found', 'status' => 'warn'];

        $htmlOnly = $categorizedPlaceholders['html_only'] ?? [];
        $htmlOnlyUsed = array_values(array_intersect($htmlOnly, $odtPlaceholders));

        if (! empty($htmlOnlyUsed)) {
            $checks[] = [
                'label' => 'HTML-only placeholders in ODT',
                'detail' => implode(', ', $htmlOnlyUsed).' — use per-domain placeholders instead',
                'status' => 'warn',
            ];
        }

        return new DocxValidationResult(
            valid: empty($missing),
            found: $found,
            missing: $missing,
            missingOptional: $missingOptional,
            extra: $extra,
            checks: $checks,
        );
    }

    public function getRepairedTemplate(string $originalPath): ?string
    {
        $tempDir = storage_path('app/temp/phpword');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true)) {
            $tempDir = sys_get_temp_dir();
        }

        $tempOdt = tempnam($tempDir, 'rst_odt_repair_').'.odt';
        if (! copy($originalPath, $tempOdt)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($tempOdt) !== true) {
            return $tempOdt;
        }

        $contentXml = $zip->getFromName('content.xml');
        if ($contentXml !== false) {
            $repaired = $this->repairOdtXml($contentXml);
            if ($contentXml !== $repaired) {
                $zip->addFromString('content.xml', $repaired);
            }
        }

        $zip->close();

        return $tempOdt;
    }

    protected function repairOdtXml(string $content): string
    {
        $content = $this->mergeOdtTextSpans($content);
        $content = $this->stripXmlFromOdtMacros($content);

        return $content;
    }

    protected function mergeOdtTextSpans(string $content): string
    {
        return preg_replace_callback(
            '/<text:(p|h)[ >].*?<\/text:\1>/s',
            function ($paragraphMatch) {
                $paragraph = $paragraphMatch[0];

                preg_match_all('/<text:span\b[^>]*>.*?<\/text:span>/s', $paragraph, $spans, PREG_OFFSET_CAPTURE);
                if (count($spans[0]) < 2) {
                    return $paragraph;
                }

                $groups = [];
                $i = 0;

                while ($i < count($spans[0])) {
                    $xml = $spans[0][$i][0];
                    $offset = $spans[0][$i][1];
                    $style = $this->extractOdtSpanStyle($xml);
                    $text = $this->extractOdtTextFromSpan($xml);

                    $group = [
                        'startOffset' => $offset,
                        'endOffset' => $offset + strlen($xml),
                        'style' => $style,
                        'text' => $text,
                        'count' => 1,
                        'firstXml' => $xml,
                    ];

                    $j = $i + 1;
                    while ($j < count($spans[0])) {
                        $nextXml = $spans[0][$j][0];
                        $nextStyle = $this->extractOdtSpanStyle($nextXml);
                        $nextText = $this->extractOdtTextFromSpan($nextXml);

                        if ($nextStyle === $style && $nextText !== '') {
                            $group['text'] .= $nextText;
                            $group['endOffset'] = $spans[0][$j][1] + strlen($nextXml);
                            $group['count']++;
                            $j++;
                        } else {
                            break;
                        }
                    }

                    $groups[] = $group;
                    $i = $j;
                }

                $wasMerged = false;
                foreach ($groups as $g) {
                    if ($g['count'] > 1) {
                        $wasMerged = true;

                        break;
                    }
                }

                if (! $wasMerged) {
                    return $paragraph;
                }

                $result = '';
                $lastEnd = 0;

                foreach ($groups as $group) {
                    $result .= substr($paragraph, $lastEnd, $group['startOffset'] - $lastEnd);

                    if ($group['count'] > 1) {
                        $result .= $this->buildMergedOdtSpan($group['firstXml'], $group['style'], $group['text']);
                    } else {
                        $result .= substr($paragraph, $group['startOffset'], $group['endOffset'] - $group['startOffset']);
                    }

                    $lastEnd = $group['endOffset'];
                }

                $result .= substr($paragraph, $lastEnd);

                return $result;
            },
            $content
        );
    }

    protected function stripXmlFromOdtMacros(string $content): string
    {
        return preg_replace_callback(
            '/\{\{(?:[^}]|<[^>]*>)*\}\}/s',
            function ($match) {
                $stripped = strip_tags($match[0]);
                $stripped = preg_replace('/\{\{\s+/', '{{', $stripped);
                $stripped = preg_replace('/\s+\}\}/', '}}', $stripped);
                $stripped = preg_replace('/\{\{(\s*)([a-zA-Z_][a-zA-Z0-9_]*)(\s*)\}\}/', '{{$2}}', $stripped);

                return $stripped;
            },
            $content
        );
    }

    protected function extractOdtSpanStyle(string $spanXml): string
    {
        if (preg_match('/<text:span\b[^>]*text:style-name="([^"]*)"[^>]*>/s', $spanXml, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function extractOdtTextFromSpan(string $spanXml): string
    {
        if (! preg_match('/^<text:span\b[^>]*>(.*)<\/text:span>$/s', $spanXml, $match)) {
            return '';
        }
        $innerXml = $match[1];

        // Replace text:s with spaces
        $innerXml = preg_replace_callback('/<text:s\b[^>]*>/i', function ($sMatch) {
            if (preg_match('/text:c="(\d+)"/i', $sMatch[0], $cMatch)) {
                return str_repeat(' ', (int) $cMatch[1]);
            }

            return ' ';
        }, $innerXml);

        // Replace tab and line break
        $innerXml = preg_replace('/<text:tab\b[^>]*\/?>/i', "\t", $innerXml);
        $innerXml = preg_replace('/<text:line-break\b[^>]*\/?>/i', "\n", $innerXml);

        return strip_tags($innerXml);
    }

    protected function buildMergedOdtSpan(string $originalXml, string $style, string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $escaped = str_replace("\n", '<text:line-break/>', $escaped);

        if ($style !== '') {
            return '<text:span text:style-name="'.$style.'">'.$escaped.'</text:span>';
        }

        return '<text:span>'.$escaped.'</text:span>';
    }

    protected function extractOdtPlaceholders(string $content): array
    {
        preg_match_all('/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    protected function convertOdtXmlToHtml(string $contentXml): string
    {
        $doc = new \DOMDocument;
        $doc->loadXML($contentXml, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Find the text body — could be office:text or a direct child of office:body
        $allTextContainers = $doc->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text');

        if ($allTextContainers->length > 0) {
            $text = $allTextContainers->item(0);
        } else {
            // Fallback: find office:body and use its children
            $bodies = $doc->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0', 'body');
            if ($bodies->length === 0) {
                return '<p class="text-muted-foreground">Could not parse ODT content.</p>';
            }
            $text = $bodies->item(0);
        }

        $html = $this->renderOdtNode($text);

        return $html;
    }

    protected function renderOdtNode(\DOMNode $node, int $depth = 0): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');

                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $localName = $child->localName;
            $ns = $child->namespaceURI;

            $textNs = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

            if ($ns === $textNs || $localName === 'p' || $localName === 'span' || $localName === 's' || $localName === 'tab' || $localName === 'line-break' || $localName === 'h') {
                switch ($localName) {
                    case 'p':
                        $inner = $this->renderOdtNode($child, $depth + 1);
                        $html .= '<p>'.$inner.'</p>';
                        break;
                    case 'h':
                        $level = $child->getAttributeNS($textNs, 'outline-level') ?: '1';
                        $inner = $this->renderOdtNode($child, $depth + 1);
                        $html .= '<h'.$level.'>'.$inner.'</h'.$level.'>';
                        break;
                    case 'span':
                        $inner = $this->renderOdtNode($child, $depth + 1);
                        $html .= '<span>'.$inner.'</span>';
                        break;
                    case 's':
                        $html .= ' ';
                        break;
                    case 'tab':
                        $html .= "\t";
                        break;
                    case 'line-break':
                        $html .= '<br>';
                        break;
                    case 'list':
                        $inner = $this->renderOdtNode($child, $depth + 1);
                        $html .= '<ul>'.$inner.'</ul>';
                        break;
                    case 'list-item':
                        $inner = $this->renderOdtNode($child, $depth + 1);
                        $html .= '<li>'.$inner.'</li>';
                        break;
                    default:
                        $html .= $this->renderOdtNode($child, $depth + 1);
                        break;
                }
            } elseif ($localName === 'table' && ($ns === 'urn:oasis:names:tc:opendocument:xmlns:table:1.0' || $ns === null)) {
                $inner = $this->renderOdtNode($child, $depth + 1);
                $html .= '<table border="1" style="border-collapse:collapse;">'.$inner.'</table>';
            } elseif ($localName === 'table-row' || $localName === 'tr') {
                $inner = $this->renderOdtNode($child, $depth + 1);
                $html .= '<tr>'.$inner.'</tr>';
            } elseif ($localName === 'table-cell' || $localName === 'td') {
                $inner = $this->renderOdtNode($child, $depth + 1);
                $html .= '<td>'.$inner.'</td>';
            } else {
                $html .= $this->renderOdtNode($child, $depth + 1);
            }
        }

        return $html;
    }
}
