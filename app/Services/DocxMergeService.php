<?php

namespace App\Services;

class DocxMergeService
{
    /**
     * Merge multiple DOCX files into a single output DOCX file using altChunk.
     *
     * @param  string[]  $docxPaths
     * @param  string  $outputPath
     * @return void
     */
    public function merge(array $docxPaths, string $outputPath): void
    {
        if (empty($docxPaths)) {
            throw new \InvalidArgumentException('No DOCX files provided for merging.');
        }

        // Ensure parent directory of output exists
        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Copy the first file to the output destination to serve as the base
        copy($docxPaths[0], $outputPath);

        if (count($docxPaths) === 1) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($outputPath) !== true) {
            throw new \RuntimeException("Failed to open output DOCX file: {$outputPath}");
        }

        // 1. Read document.xml, document.xml.rels and [Content_Types].xml
        $documentXml = $zip->getFromName('word/document.xml');
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');

        if ($documentXml === false || $relsXml === false || $contentTypesXml === false) {
            $zip->close();
            throw new \RuntimeException('Failed to read key components from base DOCX.');
        }

        // 2. Loop through other DOCX files and add them as chunks
        $relsToAppend = '';
        $contentTypesToAppend = '';
        $bodyChunks = '';

        for ($i = 1; $i < count($docxPaths); $i++) {
            $chunkId = 'rIdChunk' . $i;
            $chunkName = 'chunk' . $i . '.docx';
            $chunkPathInZip = 'word/' . $chunkName;

            // Add secondary DOCX file to zip archive
            $zip->addFromString($chunkPathInZip, file_get_contents($docxPaths[$i]));

            // Add relationship to relations XML
            $relsToAppend .= sprintf(
                '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="%s"/>',
                $chunkId,
                $chunkName
            );

            // Add content type override
            $contentTypesToAppend .= sprintf(
                '<Override PartName="/%s" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
                $chunkPathInZip
            );

            // Add a page break and altChunk element to document body
            $bodyChunks .= sprintf(
                '<w:p><w:r><w:br w:type="page"/></w:r></w:p><w:altChunk r:id="%s"/>',
                $chunkId
            );
        }

        // 3. Inject relationships into word/_rels/document.xml.rels
        // Insert right before the closing </Relationships>
        $relsXml = str_replace('</Relationships>', $relsToAppend . '</Relationships>', $relsXml);
        $zip->addFromString('word/_rels/document.xml.rels', $relsXml);

        // 4. Inject content types into [Content_Types].xml
        // Insert right before the closing </Types>
        $contentTypesXml = str_replace('</Types>', $contentTypesToAppend . '</Types>', $contentTypesXml);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);

        // 5. Inject altChunks into word/document.xml
        // The last child element of <w:body> must be the section properties (<w:sectPr>).
        // Inserting paragraphs or altChunks after <w:sectPr> corrupts the OpenXML schema.
        // Therefore, we must insert our altChunks right before the final <w:sectPr>.
        $sectPrPos = strrpos($documentXml, '<w:sectPr');
        if ($sectPrPos !== false) {
            $documentXml = substr($documentXml, 0, $sectPrPos) . $bodyChunks . substr($documentXml, $sectPrPos);
        } else {
            $documentXml = str_replace('</w:body>', $bodyChunks . '</w:body>', $documentXml);
        }
        $zip->addFromString('word/document.xml', $documentXml);

        $zip->close();
    }
}
