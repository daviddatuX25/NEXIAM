<?php

namespace App\Services;

use App\ValueObjects\DocxValidationResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class ResultSheetDocxService
{
    /**
     * Render a DOCX from a storage-relative path with placeholder replacements.
     */
    public function renderFromStoragePath(?string $docxPath, array $replacements): string
    {
        if (! $docxPath) {
            return '<p class="text-muted-foreground">No document template.</p>';
        }

        return $this->renderFromFullPath(Storage::path($docxPath), $replacements);
    }

    /**
     * Render a DOCX from an absolute filesystem path with placeholder replacements.
     */
    public function renderFromFullPath(string $fullPath, array $replacements): string
    {
        if (! is_file($fullPath)) {
            return '<p class="text-destructive">DOCX file not found.</p>';
        }

        $tempDir = storage_path('app/temp/phpword');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true)) {
            // fall back to system temp
        } else {
            Settings::setTempDir($tempDir);
        }

        $tempDocx = null;
        $tempHtml = null;

        $repairedPath = $this->getRepairedTemplate($fullPath) ?: $fullPath;

        try {
            $processor = new TemplateProcessor($repairedPath);
            $processor->setMacroChars('{{', '}}');

            $sanitized = array_map(function ($value) {
                $v = (string) $value;

                return str_replace(['{{', '}}'], ['{ {', '} }'], $v);
            }, $replacements);

            foreach ($sanitized as $key => $value) {
                $processor->setValue($key, $value);
            }

            $tempDocx = tempnam($tempDir, 'rst_').'.docx';
            $processor->saveAs($tempDocx);

            $phpWord = IOFactory::load($tempDocx);
            $tempHtml = tempnam($tempDir, 'rst_').'.html';
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            $writer->save($tempHtml);
            $html = file_get_contents($tempHtml);

            return $html ?: '<p class="text-muted-foreground">Failed to convert DOCX to HTML.</p>';
        } catch (Exception $e) {
            Log::error('DOCX render failed', ['path' => $fullPath, 'error' => $e->getMessage()]);

            return '<p class="text-destructive">Failed to process DOCX template: '
                   .htmlspecialchars($e->getMessage()).'</p>';
        } catch (\Throwable $e) {
            Log::error('DOCX render unexpected error', ['path' => $fullPath, 'error' => $e->getMessage()]);

            return '<p class="text-destructive">Unexpected error rendering template.</p>';
        } finally {
            if ($tempDocx && is_file($tempDocx)) {
                @unlink($tempDocx);
            }
            if ($tempHtml && is_file($tempHtml)) {
                @unlink($tempHtml);
            }
            if ($repairedPath && $repairedPath !== $fullPath && is_file($repairedPath)) {
                @unlink($repairedPath);
            }
        }
    }

    /**
     * Validate that a DOCX template contains all required placeholders.
     *
     * @param  array{required: string[], recommended: string[], optional: string[], html_only: string[], domain: string[], personnel: string[], institution: string[], applicant2: string[]}  $categorizedPlaceholders
     */
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
            $checks[] = ['label' => 'DOCX file readable', 'detail' => 'File not found', 'status' => 'fail'];
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

        $checks[] = ['label' => 'DOCX file readable', 'detail' => 'OK', 'status' => 'pass'];

        $repairedPath = $this->getRepairedTemplate($fullPath) ?: $fullPath;

        $processor = new TemplateProcessor($repairedPath);
        $processor->setMacroChars('{{', '}}');
        $docxPlaceholders = $processor->getVariables();

        if ($repairedPath !== $fullPath && is_file($repairedPath)) {
            @unlink($repairedPath);
        }

        $found = array_values(array_intersect($allKnown, $docxPlaceholders));

        // Missing required/recommended: what we care about most
        $missing = array_values(array_diff($requiredAndRecommended, $docxPlaceholders));
        if (in_array('applicant_reference', $missing) && (in_array('applicant_number', $docxPlaceholders) || in_array('applicant_no', $docxPlaceholders))) {
            $missing = array_values(array_diff($missing, ['applicant_reference']));
        }
        if (in_array('course_applied', $missing) && in_array('course_applied_code', $docxPlaceholders)) {
            $missing = array_values(array_diff($missing, ['course_applied']));
        }

        // Missing optional
        $missingOptional = array_values(array_diff($optionalAll, $docxPlaceholders));

        // Extra placeholders in DOCX that we don't know about
        $extra = array_values(array_diff($docxPlaceholders, $allKnown));

        // Build checks
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
        $htmlOnlyUsed = array_values(array_intersect($htmlOnly, $docxPlaceholders));

        if (! empty($htmlOnlyUsed)) {
            $checks[] = [
                'label' => 'HTML-only placeholders in DOCX',
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

    /**
     * Creates a temporary copy of the DOCX and repairs broken {{ }} macros
     * that are split across XML runs. Returns the path to the repaired file.
     *
     * Word/DOCX often fragments placeholders like {{scores_rows}} across
     * multiple <w:r> elements: <w:r><w:t>{{scores</w:t></w:r><w:r><w:t>_rows}}</w:t></w:r>
     * TemplateProcessor::setValue() can never match the full placeholder.
     *
     * This method applies two repair strategies:
     * 1. Merge adjacent <w:r> runs within each <w:p> that share the same
     *    formatting (same <w:rPr> or both missing it), concatenating their
     *    <w:t> text. This reunites most split placeholders into a single run.
     * 2. As a fallback, strip all XML tags from any content matching {{ ... }}
     *    patterns, removing XML fragmentation between the braces entirely.
     */
    public function getRepairedTemplate(string $originalPath): ?string
    {
        $tempDir = storage_path('app/temp/phpword');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true)) {
            $tempDir = sys_get_temp_dir();
        }

        $tempDocx = tempnam($tempDir, 'rst_repair_').'.docx';
        if (! copy($originalPath, $tempDocx)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($tempDocx) !== true) {
            return $tempDocx;
        }

        $filesToFix = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^word\/(document|header|footer).*\.xml$/i', $name)) {
                $filesToFix[] = $name;
            }
        }

        foreach ($filesToFix as $file) {
            $content = $zip->getFromName($file);
            if (! $content) {
                continue;
            }

            $fixedContent = $this->repairDocxXml($content);

            if ($content !== $fixedContent) {
                $zip->addFromString($file, $fixedContent);
            }
        }
        $zip->close();

        return $tempDocx;
    }

    /**
     * Repair DOCX XML content to fix fragmented {{...}} placeholders.
     *
     * Strategy 1: Merge adjacent <w:r> runs within <w:p> paragraphs that
     * have identical run properties, combining their text content.
     *
     * Strategy 2: Strip XML tags from any content between {{ and }},
     * ensuring even completely shattered placeholders are reassembled.
     */
    protected function repairDocxXml(string $content): string
    {
        $content = $this->mergeAdjacentRuns($content);
        $content = $this->stripXmlFromMacros($content);

        return $content;
    }

    /**
     * Merge adjacent <w:r> elements within <w:p> that share the same
     * formatting, concatenating their <w:t> text nodes into one run.
     *
     * This handles Word's common fragmentation pattern where a single
     * placeholder like {{scores_rows}} gets split into multiple runs
     * with identical formatting (same font, size, etc.).
     */
    protected function mergeAdjacentRuns(string $content): string
    {
        return preg_replace_callback(
            '/<w:p[ >].*?<\/w:p>/s',
            function ($paragraphMatch) {
                $paragraph = $paragraphMatch[0];

                preg_match_all('/<w:r\b[^>]*>.*?<\/w:r>/s', $paragraph, $elements, PREG_OFFSET_CAPTURE);

                if (count($elements[0]) < 2) {
                    return $paragraph;
                }

                $groups = [];
                $i = 0;

                while ($i < count($elements[0])) {
                    $xml = $elements[0][$i][0];
                    $offset = $elements[0][$i][1];
                    $rpr = $this->extractRunProperties($xml);
                    $text = $this->extractTextFromRun($xml);

                    $group = [
                        'startOffset' => $offset,
                        'endOffset' => $offset + strlen($xml),
                        'rpr' => $rpr,
                        'text' => $text,
                        'count' => 1,
                        'firstXml' => $xml,
                    ];

                    $j = $i + 1;
                    while ($j < count($elements[0])) {
                        $nextXml = $elements[0][$j][0];
                        $nextRpr = $this->extractRunProperties($nextXml);
                        $nextText = $this->extractTextFromRun($nextXml);

                        if ($nextRpr === $rpr && $nextText !== '') {
                            $group['text'] .= $nextText;
                            $group['endOffset'] = $elements[0][$j][1] + strlen($nextXml);
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
                        $result .= $this->buildMergedRun($group['firstXml'], $group['rpr'], $group['text']);
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

    /**
     * Strip XML tags from any {{ ... }} macros that still contain markup.
     * This is a fallback for cases where run merging didn't fully reassemble
     * the placeholder — e.g., when formatting differs between fragments.
     */
    protected function stripXmlFromMacros(string $content): string
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

    /**
     * Extract the <w:rPr>...</w:rPr> block from a <w:r> element, or
     * return empty string if none exists. Used to compare run formatting.
     */
    protected function extractRunProperties(string $runXml): string
    {
        if (preg_match('/<w:rPr\b[^>]*>.*?<\/w:rPr>/s', $runXml, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Extract concatenated text content from all <w:t> elements in a <w:r>.
     */
    protected function extractTextFromRun(string $runXml): string
    {
        preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $runXml, $textMatches);

        return implode('', $textMatches[1] ?? []);
    }

    /**
     * Build a merged <w:r> element preserving the run properties and
     * combining all text into a single <w:t> element.
     */
    protected function buildMergedRun(string $originalXml, string $rpr, string $text): string
    {
        $xmlPreserve = str_contains($text, "\n") || str_contains($text, "\r") || str_starts_with($text, ' ') || str_ends_with($text, ' ');
        $tAttr = $xmlPreserve ? ' xml:space="preserve"' : '';
        $textElement = "<w:t{$tAttr}>{$text}</w:t>";

        if ($rpr !== '') {
            return "<w:r>{$rpr}{$textElement}</w:r>";
        }

        return "<w:r>{$textElement}</w:r>";
    }
}
