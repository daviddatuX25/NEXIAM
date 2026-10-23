<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class NoticeTemplateBridgeWordFixture
{
    public const RELATIVE_PATH = 'fixtures/notice-bridge/reminder.docx';

    public function generate(): string
    {
        $path = database_path(self::RELATIVE_PATH);
        File::ensureDirectoryExists(dirname($path));
        $temporary = tempnam(dirname($path), '.reminder-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to prepare the notice bridge Word fixture.');
        }

        $opened = false;
        $zip = new ZipArchive;
        try {
            if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the notice bridge Word fixture.');
            }
            $opened = true;
            foreach ($this->parts() as $name => $bytes) {
                if (! $zip->addFromString($name, $bytes)
                    || ! $zip->setMtimeName($name, 1767225600)
                    || ! $zip->setCompressionName($name, ZipArchive::CM_DEFLATE)) {
                    throw new RuntimeException('Unable to write the notice bridge Word fixture.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish the notice bridge Word fixture.');
            }
            $opened = false;
            if (is_file($path) && hash_file('sha256', $path) === hash_file('sha256', $temporary)) {
                return $path;
            }
            if (! rename($temporary, $path) || ! chmod($path, 0644)) {
                throw new RuntimeException('Unable to install the notice bridge Word fixture.');
            }

            return $path;
        } finally {
            if ($opened) {
                $zip->close();
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return array<string, string> */
    private function parts(): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => $this->document(),
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/banner.png"/><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/></Relationships>',
            'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="175E70"/></w:rPr></w:style></w:styles>',
            'word/numbering.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>',
            'word/media/banner.png' => $this->banner(),
        ];
    }

    private function document(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body>
<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Entrance examination reminder</w:t></w:r></w:p>
<w:p><w:r><w:t xml:space="preserve">Dear </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>{{applicant_</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>name}}</w:t></w:r><w:r><w:t>,</w:t></w:r></w:p>
<w:p><w:r><w:t>Your examination is approaching. Review your schedule below and prepare your requirements before travelling to the testing room.</w:t></w:r></w:p>
<w:p><w:r><w:drawing><wp:inline><wp:extent cx="2286000" cy="609600"/><wp:docPr id="1" name="Synthetic reminder banner" descr="Synthetic teal and gold examination reminder banner"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="banner.png"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdImage"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="2286000" cy="609600"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>
<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/></w:tblPr><w:tblGrid><w:gridCol w:w="2400"/><w:gridCol w:w="4800"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Session</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{{session_code}}</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Date and time</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{{exam_date}} at {{exam_time}}</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Room</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{{room_name}}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Bring your examination permit and valid photo ID.</w:t></w:r></w:p>
<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:rPr><w:i/></w:rPr><w:t>Arrive 30 minutes before the scheduled start.</w:t></w:r></w:p>
<w:p><w:r><w:t xml:space="preserve">Open your applicant portal: </w:t></w:r><w:r><w:t>{{portal_url}}</w:t></w:r></w:p>
<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>
</w:body></w:document>
XML;
    }

    private function banner(): string
    {
        $teal = "\x17\x5e\x70";
        $gold = "\xe5\xb6\x4b";
        $rows = str_repeat("\x00".str_repeat($teal, 240), 48)
            .str_repeat("\x00".str_repeat($gold, 240), 16);
        $compressed = gzcompress($rows, 9);
        if ($compressed === false) {
            throw new RuntimeException('Unable to encode the synthetic reminder banner.');
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', 240, 64, 8, 2, 0, 0, 0))
            .$this->pngChunk('IDAT', $compressed)
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $bytes): string
    {
        return pack('N', strlen($bytes)).$type.$bytes.pack('N', crc32($type.$bytes));
    }
}
