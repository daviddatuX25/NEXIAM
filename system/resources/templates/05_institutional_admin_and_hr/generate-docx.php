<?php

declare(strict_types=1);

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Style\Section as SectionStyle;

$autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies not found. Run this script from an installed system/ dependency tree. Expected: {$autoload}\n");
    exit(1);
}

require $autoload;

$options = getopt('', ['data::', 'output::']);
$templateDirectory = __DIR__;
$outputDirectory = isset($options['output']) ? (string) $options['output'] : __DIR__;
$dataPath = isset($options['data']) ? (string) $options['data'] : null;

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDirectory}\n");
    exit(1);
}

$data = [];
if ($dataPath !== null) {
    if (! is_file($dataPath) || ! is_readable($dataPath)) {
        fwrite(STDERR, "Data file is not readable: {$dataPath}\n");
        exit(1);
    }

    try {
        $decoded = json_decode((string) file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid JSON data file: {$exception->getMessage()}\n");
        exit(1);
    }

    if (! is_array($decoded)) {
        fwrite(STDERR, "The JSON data root must be an object keyed by template filename.\n");
        exit(1);
    }

    $data = $decoded;
}

$templates = [
    'universal-staff-accomplishment-report',
    'institutional-admissions-funnel',
    'campus-venue-capacity-audit',
    'board-of-regents-admissions-packet',
];

foreach ($templates as $templateName) {
    $templatePath = $templateDirectory.'/'.$templateName.'.html';
    if (! is_file($templatePath) || ! is_readable($templatePath)) {
        fwrite(STDERR, "Template is missing or unreadable: {$templatePath}\n");
        exit(1);
    }

    $templateData = $data[$templateName] ?? [];
    if (! is_array($templateData)) {
        fwrite(STDERR, "Data for {$templateName} must be a JSON object.\n");
        exit(1);
    }

    $html = renderTemplate((string) file_get_contents($templatePath), $templateData);
    $document = new PhpWord;
    $document->setDefaultFontSize(9);
    $document->getDocInfo()->setTitle(documentTitle($templateName));
    $document->getDocInfo()->setSubject('ISPSC Internal Communication Letterhead · ISPSC-QAD-F032a Rev. 5 (07-07-2025)');
    $document->getDocInfo()->setDescription('Generated report template. Populate only with verified, authorized institutional source data.');

    $landscape = in_array($templateName, ['institutional-admissions-funnel', 'campus-venue-capacity-audit'], true);
    $section = $document->addSection([
        'orientation' => $landscape ? SectionStyle::ORIENTATION_LANDSCAPE : SectionStyle::ORIENTATION_PORTRAIT,
        'marginTop' => 1100,
        'marginBottom' => 1100,
        'marginLeft' => 1000,
        'marginRight' => 1000,
    ]);

    $header = $section->addHeader();
    $header->addText('ILOCOS SUR POLYTECHNIC STATE COLLEGE', ['bold' => true, 'size' => 13, 'color' => '145B41'], ['alignment' => 'center']);
    $header->addText('Tagudin Campus · Tagudin, Ilocos Sur', ['size' => 9, 'color' => '42574D'], ['alignment' => 'center']);
    $header->addText('ISPSC Internal Communication Letterhead | ISPSC-QAD-F032a · Rev. 5 (07-07-2025)', ['size' => 8, 'color' => '526158'], ['alignment' => 'center', 'spaceAfter' => 100]);
    $section->addTextBreak(1);

    $body = htmlBody($html);
    Html::addHtml($section, $body, false, false);

    $footer = $section->addFooter();
    $footer->addText('ISPSC Tagudin Campus · '.documentTitle($templateName).' · Generated report template', ['size' => 7, 'color' => '59665E'], ['alignment' => 'center']);

    $destination = rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$templateName.'.docx';
    IOFactory::createWriter($document, 'Word2007')->save($destination);
    fwrite(STDOUT, "Generated {$destination}\n");
}

/**
 * Expand the lightweight repeat blocks and safely replace scalar template tags.
 * Input values must be sourced from authorized records; absent scalar values remain visible as tokens.
 *
 * @param  array<string, mixed>  $data
 */
function renderTemplate(string $html, array $data): string
{
    $html = preg_replace_callback('/\{\{#each\s+([\w.]+)\}\}(.*?)\{\{\/each\}\}/s', static function (array $match) use ($data): string {
        $rows = dataValue($data, $match[1]);
        if (! is_array($rows)) {
            return '';
        }

        $rendered = '';
        foreach ($rows as $row) {
            if (is_array($row)) {
                $rendered .= replaceScalarTags($match[2], $row);
            }
        }

        return $rendered;
    }, $html) ?? $html;

    return replaceScalarTags($html, $data);
}

/**
 * @param  array<string, mixed>  $values
 */
function replaceScalarTags(string $html, array $values): string
{
    return preg_replace_callback('/\{\{([\w.]+)\}\}/', static function (array $match) use ($values): string {
        $value = dataValue($values, $match[1]);
        if (! is_scalar($value) && $value !== null) {
            return $match[0];
        }

        return $value === null ? $match[0] : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }, $html) ?? $html;
}

/**
 * @param  array<string, mixed>  $values
 */
function dataValue(array $values, string $path): mixed
{
    $value = $values;
    foreach (explode('.', $path) as $key) {
        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return null;
        }

        $value = $value[$key];
    }

    return $value;
}

function htmlBody(string $html): string
{
    $previousSetting = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"><!doctype html>'.$html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);

    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//style | //script | //header | //footer') ?: [] as $node) {
        $node->parentNode?->removeChild($node);
    }

    $body = $xpath->query('//body')->item(0);
    if ($body instanceof DOMElement) {
        $content = '';
        foreach ($body->childNodes as $child) {
            $content .= $document->saveHTML($child);
        }

        return $content;
    }

    return $document->saveHTML() ?: $html;
}

function documentTitle(string $templateName): string
{
    return match ($templateName) {
        'universal-staff-accomplishment-report' => 'Universal Staff Accomplishment Report',
        'institutional-admissions-funnel' => 'Institutional Admissions Funnel',
        'campus-venue-capacity-audit' => 'Campus Venue Capacity Audit',
        'board-of-regents-admissions-packet' => 'Board of Regents Admissions Packet',
        default => $templateName,
    };
}
