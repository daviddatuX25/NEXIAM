<?php

namespace App\ResultSheet\Rendering;

use RuntimeException;
use ZipArchive;

/**
 * Merges filled DOCX payloads using OpenXML altChunk relationships.
 * Ported from the proven legacy DocxMergeService, kept behind one adapter.
 *
 * @param  list<string>  $documents
 */
class DocxMerger
{
    public function merge(array $documents): string
    {
        if ($documents === []) {
            throw new RuntimeException('No DOCX documents to merge.');
        }
        if (count($documents) === 1) {
            return $documents[0];
        }

        $dir = storage_path('app/temp/docx-merge');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $output = tempnam($dir, 'merged_').'.docx';
        if ($output === false) {
            throw new RuntimeException('Unable to allocate DOCX merge path.');
        }
        file_put_contents($output, $documents[0]);

        $zip = new ZipArchive;
        if ($zip->open($output) !== true) {
            throw new RuntimeException('Unable to open base DOCX.');
        }
        $documentXml = $zip->getFromName('word/document.xml');
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $typesXml = $zip->getFromName('[Content_Types].xml');
        if ($documentXml === false || $relsXml === false || $typesXml === false) {
            $zip->close();
            @unlink($output);
            throw new RuntimeException('Base DOCX is missing required OpenXML parts.');
        }

        $rels = '';
        $types = '';
        $chunks = '';
        for ($i = 1; $i < count($documents); $i++) {
            $rid = 'rIdPrintChunk'.$i;
            $name = 'print-chunk-'.$i.'.docx';
            $zip->addFromString('word/'.$name, $documents[$i]);
            $rels .= '<Relationship Id="'.$rid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="'.$name.'"/>';
            $types .= '<Override PartName="/word/'.$name.'" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>';
            $chunks .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p><w:altChunk r:id="'.$rid.'"/>';
        }

        $relsXml = str_replace('</Relationships>', $rels.'</Relationships>', $relsXml);
        $typesXml = str_replace('</Types>', $types.'</Types>', $typesXml);
        $sectPos = strrpos($documentXml, '<w:sectPr');
        $documentXml = $sectPos === false
            ? str_replace('</w:body>', $chunks.'</w:body>', $documentXml)
            : substr($documentXml, 0, $sectPos).$chunks.substr($documentXml, $sectPos);

        $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
        $zip->addFromString('[Content_Types].xml', $typesXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $bytes = (string) file_get_contents($output);
        @unlink($output);

        return $bytes;
    }
}
