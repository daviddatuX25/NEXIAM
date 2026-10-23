<?php

namespace App\DocumentTemplate\Rendering;

use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\DocumentTemplate\Support\DocxMacroRepairer;
use App\DocumentTemplate\Support\DocxShapeAssetInjector;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

/** Fills DOCX and ODT templates with scalar or tabular replacement data. */
class DocxTemplateFiller
{
    private const PAGINATION_MACROS = ['page_i', 'page_all', 'pg_i', 'pg_all'];

    public function __construct(
        private readonly DocxMacroRepairer $repairer,
        private readonly ?DynamicAssetResolutionService $assetService = null,
    ) {}

    /**
     * @param  array<string, mixed>  $replacements
     * @param  null|string|array<array-key, mixed>  $repeatingKey
     */
    public function fill(string $sourcePath, array $replacements, null|string|array $repeatingKey = null): string
    {
        $repeatingRegions = match (true) {
            $repeatingKey === null => [],
            is_string($repeatingKey) => [$repeatingKey],
            default => array_values(array_filter($repeatingKey, 'is_string')),
        };

        return strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'odt'
            ? $this->fillOdt($sourcePath, $replacements)
            : $this->fillDocx($sourcePath, $replacements, $repeatingRegions);
    }

    /**
     * @param  array<string, mixed>  $replacements
     * @param  list<string>  $repeatingRegions
     */
    private function fillDocx(string $sourcePath, array $replacements, array $repeatingRegions): string
    {
        $tempDir = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $this->allocateDocxPath($tempDir, 'docx_filled_');
        $repairedSource = $sourcePath;
        $completed = false;
        $escapingWasEnabled = Settings::isOutputEscapingEnabled();

        try {
            $repairedSource = $this->repairSource($sourcePath, $tempDir);
            Settings::setOutputEscapingEnabled(true);
            $processor = new TemplateProcessor($repairedSource);
            $processor->setMacroChars('{{', '}}');

            foreach ($replacements as $key => $value) {
                if (in_array((string) $key, self::PAGINATION_MACROS, true)) {
                    continue;
                }

                if (in_array((string) $key, $repeatingRegions, true) && is_array($value)) {
                    $this->fillRepeatingRows($processor, (string) $key, $value);

                    continue;
                }

                if (DocxShapeAssetInjector::isAssetKey((string) $key)) {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $processor->setValue((string) $key, $this->protectLiteralPaginationMacros((string) ($value ?? '')));
                }
            }
            $processor->setValue('page_i', '__NEXIAM_PAGE_I__');
            $processor->setValue('page_all', '__NEXIAM_PAGE_ALL__');
            $processor->setValue('pg_i', '__NEXIAM_PAGE_I__');
            $processor->setValue('pg_all', '__NEXIAM_PAGE_ALL__');
            $processor->saveAs($tempFile);
            DocxShapeAssetInjector::inject($tempFile, $replacements, $this->assetService);
            $this->injectNativePaginationFields($tempFile);
            $completed = true;
        } finally {
            Settings::setOutputEscapingEnabled($escapingWasEnabled);
            if ($repairedSource !== $sourcePath && is_file($repairedSource)) {
                @unlink($repairedSource);
            }
            if (! $completed) {
                @unlink($tempFile);
            }
        }

        return $tempFile;
    }

    private function injectNativePaginationFields(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Unable to open filled document archive for pagination injection.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name)
                    || ($name !== 'word/document.xml'
                        && preg_match('#^word/(?:header|footer)[^/]*\.xml$#', $name) !== 1)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (! is_string($xml)) {
                    throw new RuntimeException("Unable to read document part for pagination injection: {$name}");
                }

                $updatedXml = preg_replace_callback(
                    '#(<w:t\b[^>]*>)(.*?)(</w:t>)#s',
                    function (array $textRun): string {
                        $content = preg_replace_callback(
                            '/\{\{\s*(page_i|pg_i|page_all|pg_all)\s*\}\}|__NEXIAM_PAGE_(I|ALL)__/i',
                            function (array $macro): string {
                                $token = strtoupper($macro[1] !== '' ? $macro[1] : $macro[2]);
                                $instruction = in_array($token, ['PAGE_ALL', 'PG_ALL', 'ALL'], true)
                                    ? 'NUMPAGES'
                                    : 'PAGE';

                                return $this->paginationFieldXml($instruction);
                            },
                            $textRun[2],
                        );

                        if ($content === null) {
                            throw new RuntimeException('Unable to inject native pagination fields into document text.');
                        }

                        $content = preg_replace_callback(
                            '/__NEXIAM_LITERAL_([a-f0-9]+)__/',
                            static function (array $literal): string {
                                $decoded = hex2bin($literal[1]);

                                return $decoded === false ? $literal[0] : $decoded;
                            },
                            $content,
                        );
                        if ($content === null) {
                            throw new RuntimeException('Unable to restore literal pagination macros in document text.');
                        }

                        return $textRun[1].$content.$textRun[3];
                    },
                    $xml,
                );

                if ($updatedXml === null) {
                    throw new RuntimeException("Unable to process document part for pagination injection: {$name}");
                }

                if ($updatedXml !== $xml && ! $zip->addFromString($name, $updatedXml)) {
                    throw new RuntimeException("Unable to update document part with pagination fields: {$name}");
                }
            }

            $settingsIndex = $zip->locateName('word/settings.xml');
            if ($settingsIndex !== false) {
                $settingsXml = $zip->getFromIndex($settingsIndex);
                if (! is_string($settingsXml)) {
                    throw new RuntimeException('Unable to read document settings for pagination updates.');
                }

                $settingsXml = preg_replace(
                    '#<w:updateFields\b[^>]*(?:/>|>.*?</w:updateFields\s*>)#s',
                    '<w:updateFields w:val="true"/>',
                    $settingsXml,
                    1,
                    $updatedSettingsCount,
                );
                if ($settingsXml === null) {
                    throw new RuntimeException('Unable to update document field-refresh settings.');
                }

                if ($updatedSettingsCount === 0) {
                    $settingsXml = preg_replace(
                        '#</w:settings\s*>#',
                        '<w:updateFields w:val="true"/></w:settings>',
                        $settingsXml,
                        1,
                        $updatedSettingsCount,
                    );
                    if ($settingsXml === null || $updatedSettingsCount === 0) {
                        throw new RuntimeException('Unable to add field-refresh settings to the document.');
                    }
                }

                if (! $zip->addFromString('word/settings.xml', $settingsXml)) {
                    throw new RuntimeException('Unable to save document field-refresh settings.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function paginationFieldXml(string $instruction): string
    {
        return '</w:t></w:r><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> '.$instruction.' </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r><w:r><w:t xml:space="preserve">';
    }

    private function protectLiteralPaginationMacros(string $value): string
    {
        if (! str_contains($value, '{{') && ! str_contains($value, '__NEXIAM_PAGE_')) {
            return $value;
        }

        return preg_replace_callback(
            '/\{\{\s*(?:page_i|pg_i|page_all|pg_all)\s*\}\}|__NEXIAM_PAGE_(?:I|ALL)__/i',
            static fn (array $macro): string => '__NEXIAM_LITERAL_'.bin2hex($macro[0]).'__',
            $value,
        ) ?? $value;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillRepeatingRows(TemplateProcessor $processor, string $repeatingKey, array $rows): void
    {
        $variables = $processor->getVariables();
        $qualifiedMarker = null;
        if (is_array($rows[0] ?? null)) {
            foreach (array_keys($rows[0]) as $colKey) {
                if (in_array("{$repeatingKey}.{$colKey}", $variables, true)) {
                    $qualifiedMarker = "{$repeatingKey}.{$colKey}";
                    break;
                }
            }
        }
        $qualified = $qualifiedMarker !== null;
        $rowMarker = $qualified ? $qualifiedMarker : $repeatingKey;

        if ($rows === [] && $qualified === false) {
            $rowMarker = collect($variables)->first(fn (string $variable): bool => str_starts_with($variable, "{$repeatingKey}."))
                ?? $repeatingKey;
            $qualified = str_starts_with($rowMarker, "{$repeatingKey}.");
        }

        if (! in_array($rowMarker, $variables, true)) {
            return;
        }
        $processor->cloneRow($rowMarker, max(1, count($rows)));

        if ($rows === []) {
            foreach ($processor->getVariables() as $variable) {
                $belongsToRegion = $variable === "{$repeatingKey}#1"
                    || (str_starts_with($variable, "{$repeatingKey}.") && str_ends_with($variable, '#1'));

                if ($belongsToRegion) {
                    $processor->setValue($variable, '-');
                }
            }

            return;
        }

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowNum = $index + 1;
            foreach ($row as $colKey => $colVal) {
                if (is_scalar($colVal) || $colVal === null) {
                    $variable = $qualified ? "{$repeatingKey}.{$colKey}" : (string) $colKey;
                    $processor->setValue("{$variable}#{$rowNum}", $this->protectLiteralPaginationMacros((string) ($colVal ?? '')));
                }
            }
        }
    }

    private function repairSource(string $sourcePath, string $tempDir): string
    {
        $zip = new ZipArchive;
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open document archive.');
        }

        try {
            $repairedParts = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name)
                    || ($name !== 'word/document.xml'
                        && preg_match('#^word/(?:header|footer)\d+\.xml$#', $name) !== 1)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (! is_string($xml)) {
                    continue;
                }

                $repaired = $this->repairer->repairXml($xml);
                if ($repaired !== $xml) {
                    $repairedParts[$name] = $repaired;
                }
            }
        } finally {
            $zip->close();
        }

        if ($repairedParts === []) {
            return $sourcePath;
        }

        $copy = $this->allocateDocxPath($tempDir, 'docx_repaired_');

        if (! copy($sourcePath, $copy)) {
            @unlink($copy);
            throw new RuntimeException('Unable to copy document for macro repair.');
        }

        $zip = new ZipArchive;
        if ($zip->open($copy) !== true) {
            @unlink($copy);
            throw new RuntimeException('Unable to open repaired document archive.');
        }

        try {
            foreach ($repairedParts as $name => $xml) {
                if (! $zip->addFromString($name, $xml)) {
                    throw new RuntimeException("Unable to repair document part: {$name}");
                }
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($copy);
            throw $exception;
        }

        $zip->close();

        return $copy;
    }

    private function allocateDocxPath(string $directory, string $prefix): string
    {
        $allocated = tempnam($directory, $prefix);
        if ($allocated === false) {
            throw new RuntimeException('Unable to allocate a private document path.');
        }
        $path = $allocated.'.docx';
        if (! rename($allocated, $path)) {
            @unlink($allocated);
            throw new RuntimeException('Unable to allocate a private DOCX path.');
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    private function fillOdt(string $sourcePath, array $replacements): string
    {
        $tempDir = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = tempnam($tempDir, 'odt_filled_').'.odt';
        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate a filled document path.');
        }

        copy($sourcePath, $tempFile);
        $zip = new ZipArchive;
        if ($zip->open($tempFile) !== true) {
            throw new RuntimeException('Unable to open filled ODT archive.');
        }

        $content = $zip->getFromName('content.xml');
        if ($content === false) {
            $zip->close();
            throw new RuntimeException('ODT content.xml is missing.');
        }

        foreach ($replacements as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $content = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $content);
            }
        }

        $zip->addFromString('content.xml', $content);
        $zip->close();

        return $tempFile;
    }
}
