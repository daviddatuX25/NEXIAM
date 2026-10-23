<?php

namespace App\DocumentTemplate\Support;

use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use ZipArchive;

final class DocxShapeAssetInjector
{
    private const DEFAULT_EXTENT_EMU = 1828800;

    private const IMAGE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

    /**
     * @param  array<string, mixed>  $replacements
     */
    public static function inject(string $docxPath, array $replacements, ?DynamicAssetResolutionService $assetService = null): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return false;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (! is_string($documentXml) || $documentXml === '') {
            $zip->close();

            return false;
        }

        $service = $assetService ?? new DynamicAssetResolutionService;
        $relationships = $zip->getFromName('word/_rels/document.xml.rels');
        $relationships = is_string($relationships) && $relationships !== ''
            ? $relationships
            : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        $contentTypes = is_string($contentTypes) && $contentTypes !== ''
            ? $contentTypes
            : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>';

        $shapes = [];
        preg_match_all('/<wps:wsp\b[^>]*>.*?<\/wps:wsp>/s', $documentXml, $drawingMatches, PREG_OFFSET_CAPTURE);
        foreach ($drawingMatches[0] as [$shapeXml, $offset]) {
            $shapes[] = ['xml' => $shapeXml, 'offset' => $offset, 'type' => 'drawing'];
        }
        preg_match_all('/<v:shape\b[^>]*>.*?<\/v:shape>/s', $documentXml, $vmlMatches, PREG_OFFSET_CAPTURE);
        foreach ($vmlMatches[0] as [$shapeXml, $offset]) {
            if (preg_match('/<v:textbox\b/i', $shapeXml) === 1) {
                $shapes[] = ['xml' => $shapeXml, 'offset' => $offset, 'type' => 'vml'];
            }
        }

        usort($shapes, static fn (array $left, array $right): int => $right['offset'] <=> $left['offset']);
        $assetIndex = 0;
        foreach ($shapes as $shape) {
            $key = self::findAssetKey($shape['xml'], $replacements);
            if ($key === null) {
                continue;
            }

            $isQr = str_contains(strtolower($key), 'qr');
            if ($isQr) {
                $url = $replacements[$key] ?? $replacements['verification_qr'] ?? 'https://nexiam.test/verify';
                $imageBytes = $service->renderVerificationQr((string) $url);
            } else {
                [$cx, $cy] = self::extractExtent($documentXml, $shape['offset'], strlen($shape['xml']));
                $path = $replacements[$key] ?? $replacements['applicant_photo_2x2'] ?? null;
                $imageBytes = $service->resolveCandidatePhoto(is_string($path) ? $path : null, $cx, $cy);
            }

            while ($zip->locateName('word/media/shape_asset_'.$assetIndex.'.png') !== false
                || preg_match('/\bId="rIdAsset'.$assetIndex.'"/', $relationships) === 1) {
                $assetIndex++;
            }

            $mediaPath = 'word/media/shape_asset_'.$assetIndex.'.png';
            $relationshipId = 'rIdAsset'.$assetIndex;
            if (! $zip->addFromString($mediaPath, $imageBytes)) {
                $zip->close();

                return false;
            }

            $shapeXml = self::injectImage($shape['xml'], $shape['type'], $relationshipId);
            $documentXml = substr_replace($documentXml, $shapeXml, $shape['offset'], strlen($shape['xml']));
            $relationships = self::appendRelationship($relationships, $relationshipId, basename($mediaPath));
            $assetIndex++;
        }

        if (preg_match('/<Default\b(?=[^>]*\bExtension="png")(?=[^>]*\bContentType="image\/png")[^>]*\/>/i', $contentTypes) !== 1) {
            $contentTypes = preg_replace('/<\/Types>/', '<Default Extension="png" ContentType="image/png"/></Types>', $contentTypes, 1) ?? $contentTypes;
        }

        $success = $zip->addFromString('word/document.xml', $documentXml)
            && $zip->addFromString('word/_rels/document.xml.rels', $relationships)
            && $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->close();

        return $success;
    }

    public static function isAssetKey(string $key): bool
    {
        $normalizedKey = trim($key, "{} \t\n\r\0\x0B");
        $lowerKey = strtolower($normalizedKey);

        return $normalizedKey === 'applicant_photo_2x2'
            || $normalizedKey === 'verification_qr'
            || str_starts_with($lowerKey, 'photo_')
            || str_starts_with($lowerKey, 'qr_')
            || str_contains($lowerKey, 'photo')
            || str_contains($lowerKey, 'qr');
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    private static function findAssetKey(string $shapeXml, array $replacements): ?string
    {
        $shapeText = html_entity_decode(strip_tags($shapeXml), ENT_QUOTES | ENT_XML1);
        foreach ($replacements as $replacementKey => $replacementValue) {
            $normalizedKey = trim((string) $replacementKey, "{} \t\n\r\0\x0B");
            $lowerKey = strtolower($normalizedKey);
            if ((str_contains($lowerKey, 'photo') || str_contains($lowerKey, 'qr'))
                && is_string($replacementValue) && $replacementValue !== ''
                && str_contains($shapeText, $replacementValue)) {
                return $normalizedKey;
            }
        }

        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', html_entity_decode(strip_tags($shapeXml), ENT_QUOTES | ENT_XML1), $matches);
        foreach ($matches[1] as $placeholder) {
            $key = trim($placeholder);
            $normalizedKey = trim($key, "{} \t\n\r\0\x0B");
            $lowerKey = strtolower($normalizedKey);
            if ($normalizedKey === 'applicant_photo_2x2' || $normalizedKey === 'verification_qr'
                || str_starts_with($lowerKey, 'photo_') || str_starts_with($lowerKey, 'qr_')
                || (array_key_exists($normalizedKey, $replacements)
                    && (str_contains($lowerKey, 'photo') || str_contains($lowerKey, 'qr')))) {
                return $normalizedKey;
            }
        }

        return null;
    }

    /** @return array{int, int} */
    private static function extractExtent(string $documentXml, int $shapeOffset, int $shapeLength): array
    {
        $start = strrpos(substr($documentXml, 0, $shapeOffset), '<w:drawing');
        $end = strpos($documentXml, '</w:drawing>', $shapeOffset + $shapeLength);
        $drawingXml = $start !== false && $end !== false
            ? substr($documentXml, $start, $end + strlen('</w:drawing>') - $start)
            : substr($documentXml, $shapeOffset, $shapeLength);

        if (preg_match('/<wp:extent\b[^>]*\bcx="(\d+)"[^>]*\bcy="(\d+)"[^>]*\/?\s*>/i', $drawingXml, $extent) === 1) {
            return [(int) $extent[1], (int) $extent[2]];
        }

        return [self::DEFAULT_EXTENT_EMU, self::DEFAULT_EXTENT_EMU];
    }

    private static function injectImage(string $shapeXml, string $type, string $relationshipId): string
    {
        if ($type === 'vml') {
            $imageData = '<v:imagedata r:id="'.$relationshipId.'" o:title=""/>';
            if (preg_match('/<v:imagedata\b[^>]*\/>/i', $shapeXml) === 1) {
                $shapeXml = preg_replace('/<v:imagedata\b[^>]*\/>/i', $imageData, $shapeXml, 1) ?? $shapeXml;
            } else {
                $shapeXml = preg_replace('/(<v:shape\b[^>]*>)/i', '$1'.$imageData, $shapeXml, 1) ?? $shapeXml;
            }

            return preg_replace_callback('/(<v:textbox\b[^>]*>).*?(<\/v:textbox>)/is', static fn (array $matches): string => $matches[1].$matches[2], $shapeXml, 1) ?? $shapeXml;
        }

        $fill = '<a:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$relationshipId.'" cstate="print"/><a:stretch><a:fillRect/></a:stretch></a:blipFill>';
        if (preg_match('/<a:blipFill\b[^>]*>.*?<\/a:blipFill>/s', $shapeXml) === 1) {
            $shapeXml = preg_replace('/<a:blipFill\b[^>]*>.*?<\/a:blipFill>/s', $fill, $shapeXml, 1) ?? $shapeXml;
        } elseif (preg_match('/<wps:spPr\b[^>]*>/', $shapeXml) === 1) {
            if (preg_match('/<\/a:(?:prstGeom|custGeom)>/', $shapeXml) === 1) {
                $shapeXml = preg_replace_callback('/<a:(?:prstGeom|custGeom)\b[^>]*>.*?<\/a:(?:prstGeom|custGeom)>/s', static fn (array $matches): string => $matches[0].$fill, $shapeXml, 1) ?? $shapeXml;
            } elseif (preg_match('/<\/a:xfrm>/', $shapeXml) === 1) {
                $shapeXml = preg_replace('/<\/a:xfrm>/', '</a:xfrm>'.$fill, $shapeXml, 1) ?? $shapeXml;
            } else {
                $shapeXml = preg_replace('/(<wps:spPr\b[^>]*>)/', '$1'.$fill, $shapeXml, 1) ?? $shapeXml;
            }
        }

        return preg_replace_callback(
            '/(<wps:txbxContent\b[^>]*>)(.*?)(<\/wps:txbxContent>)/s',
            static fn (array $matches): string => $matches[1].preg_replace('/<w:t\b[^>]*>.*?<\/w:t>/s', '<w:t></w:t>', $matches[2]).$matches[3],
            $shapeXml,
            1,
        ) ?? $shapeXml;
    }

    private static function appendRelationship(string $relationships, string $id, string $target): string
    {
        $relationship = '<Relationship Id="'.$id.'" Type="'.self::IMAGE_RELATIONSHIP_TYPE.'" Target="media/'.$target.'"/>';

        return preg_replace('/<\/Relationships>/', $relationship.'</Relationships>', $relationships, 1) ?? $relationships;
    }
}
