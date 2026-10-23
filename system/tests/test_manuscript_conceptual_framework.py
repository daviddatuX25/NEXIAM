"""Behavior checks for bounded conceptual-framework prose insertion."""
import importlib.util
from pathlib import Path
import tempfile
import unittest
from zipfile import ZipFile

from lxml import etree as ET

SPEC = importlib.util.spec_from_file_location(
    'insert_manuscript_conceptual_framework',
    Path(__file__).resolve().parents[1] / 'bin' / 'insert-manuscript-conceptual-framework.py',
)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)
W = MODULE.W
WP = MODULE.WP
WPG = MODULE.WPG
A = MODULE.A
NS = MODULE.NS


class ConceptualFrameworkInsertionTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='nexiam-conceptual-test-')
        self.addCleanup(self.scratch.cleanup)
        self.root = Path(self.scratch.name)
        self.source = self.root / 'source.docx'
        self.content = self.root / 'conceptual.md'
        self.output = self.root / 'output.docx'
        self.paragraphs = [
            'Framework summary preserves the **NEXIAM** model and Figure 1.',
        ]
        markdown = chr(10).join([
            '# Chapter 1: Introduction', '', '## 1.2 Conceptual Framework of the Study', '',
            self.paragraphs[0], '', '```mermaid', 'flowchart LR', ' A-->B', '```', '',
            '**Figure 1. IPO Diagram**',
        ])
        self.content.write_text(markdown, encoding='utf-8')
        self.document = ET.Element(f'{{{W}}}document', nsmap={'w': W})
        self.body = ET.SubElement(self.document, f'{{{W}}}body')
        self.paragraph('Prior body specimen.', 'Textbody')
        self.paragraph('Conceptual Framework of the Study', 'Heading3')
        self.old = [self.paragraph(f'Legacy prose {i}.', 'Textbody') for i in range(3)]
        self.caption = self.paragraph('Figure 1. IPO Diagram', 'Caption')
        drawing_paragraph = self.paragraph('', None)
        drawing_properties = ET.SubElement(drawing_paragraph, f'{{{W}}}pPr')
        ET.SubElement(drawing_properties, f'{{{W}}}keepNext')
        drawing = ET.SubElement(drawing_paragraph, f'{{{W}}}drawing')
        inline = ET.SubElement(drawing, f'{{{WP}}}inline')
        ET.SubElement(inline, f'{{{WP}}}extent', cx='100', cy='200')
        graphic = ET.SubElement(inline, f'{{{A}}}graphic')
        graphic_data = ET.SubElement(graphic, f'{{{A}}}graphicData', uri='http://schemas.microsoft.com/office/word/2010/wordprocessingGroup')
        group = ET.SubElement(graphic_data, f'{{{WPG}}}wgp')
        group_properties = ET.SubElement(group, f'{{{WPG}}}grpSpPr')
        transform = ET.SubElement(group_properties, f'{{{A}}}xfrm')
        ET.SubElement(transform, f'{{{A}}}off', x='0', y='0')
        ET.SubElement(transform, f'{{{A}}}ext', cx='100', cy='200')
        ET.SubElement(transform, f'{{{A}}}chOff', x='0', y='0')
        ET.SubElement(transform, f'{{{A}}}chExt', cx='100', cy='200')
        shape = ET.SubElement(group, f'{{{WPG}}}grpSp')
        ET.SubElement(shape, f'{{{WPG}}}cNvPr', id='1', name='Editable IPO')
        ET.SubElement(shape, f'{{{WPG}}}grpSpPr')
        shape_text = ET.SubElement(shape, f'{{{W}}}txbxContent')
        text_paragraph = ET.SubElement(shape_text, f'{{{W}}}p')
        ET.SubElement(ET.SubElement(text_paragraph, f'{{{W}}}r'), f'{{{W}}}t').text = 'Input'
        self.native_group = group
        self.objective = self.paragraph('Objectives of the Study', 'Heading3')
        ET.SubElement(self.objective, f'{{{W}}}bookmarkStart', {f'{{{W}}}id': '20', f'{{{W}}}name': 'objectives'})
        ET.SubElement(self.objective, f'{{{W}}}bookmarkEnd', {f'{{{W}}}id': '20'})
        self.paragraph('Objective prose remains unchanged.', 'Textbody')
        ET.SubElement(self.body, f'{{{W}}}sectPr')
        self.footer = b'<footer font="Edwardian Script ITC" size="16" />'
        self.save_source()

    def paragraph(self, value, style):
        paragraph = ET.SubElement(self.body, f'{{{W}}}p')
        if style:
            properties = ET.SubElement(paragraph, f'{{{W}}}pPr')
            ET.SubElement(properties, f'{{{W}}}pStyle', {f'{{{W}}}val': style})
        if value:
            run = ET.SubElement(paragraph, f'{{{W}}}r')
            ET.SubElement(run, f'{{{W}}}t').text = value
        return paragraph

    def save_source(self):
        with ZipFile(self.source, 'w') as archive:
            archive.writestr('word/document.xml', ET.tostring(self.document))
            archive.writestr('word/footer1.xml', self.footer)

    def test_replaces_prose_keeps_ipo_and_caption_and_fits_group_aspect(self):
        source_before = self.source.read_bytes()
        MODULE.insert_conceptual_framework(self.source, self.content, self.output)
        with ZipFile(self.output) as archive:
            result = ET.fromstring(archive.read('word/document.xml'))
            self.assertEqual(archive.read('word/footer1.xml'), self.footer)
        body = result.find('w:body', NS)
        heading = next(p for p in body if MODULE.text(p) == 'Conceptual Framework of the Study')
        objective = next(p for p in body if MODULE.text(p) == 'Objectives of the Study')
        between = list(body)[body.index(heading) + 1:body.index(objective)]
        inserted = [p for p in between if p.find('w:pPr/w:pStyle[@w:val="Textbody"]', NS) is not None]
        self.assertEqual([MODULE.text(p) for p in inserted], [p.replace('**', '') for p in self.paragraphs])
        self.assertEqual(len(inserted), 1)
        caption = next(p for p in between if p.find('w:pPr/w:pStyle[@w:val="Caption"]', NS) is not None)
        self.assertEqual(MODULE.text(caption), 'Figure 1. IPO Diagram')
        self.assertIsNotNone(caption.find('w:pPr/w:keepNext', NS))
        self.assertIsNotNone(heading.find('w:pPr/w:keepNext', NS))
        self.assertIsNotNone(objective.find('w:pPr/w:keepNext', NS))
        drawing = next(p for p in between if p.find('.//w:drawing', NS) is not None)
        self.assertIsNone(drawing.find('w:pPr/w:keepNext', NS))
        group = drawing.find('.//wpg:wgp', NS)
        self.assertEqual(MODULE.text(group), 'Input')
        inline_extent = drawing.find('.//wp:inline/wp:extent', NS)
        group_extent = group.find('wpg:grpSpPr/a:xfrm/a:ext', NS)
        coordinates = group.find('wpg:grpSpPr/a:xfrm/a:chExt', NS)
        self.assertEqual(inline_extent.get('cy'), group_extent.get('cy'))
        self.assertAlmostEqual(int(inline_extent.get('cy')) / int(inline_extent.get('cx')),
                               int(coordinates.get('cy')) / int(coordinates.get('cx')), places=6)
        bold_paragraph = inserted[0]
        bold = [MODULE.text(r) for r in bold_paragraph.findall('w:r', NS) if r.find('w:rPr/w:b', NS) is not None]
        self.assertEqual(bold, ['NEXIAM'])
        self.assertEqual(self.source.read_bytes(), source_before)

    def test_refuses_unsafe_overwrite_paths(self):
        original = self.source.read_bytes()
        with self.assertRaisesRegex(ValueError, 'fresh output'):
            MODULE.insert_conceptual_framework(self.source, self.content, self.source)
        self.output.write_bytes(b'keep-existing')
        with self.assertRaisesRegex(ValueError, 'fresh output'):
            MODULE.insert_conceptual_framework(self.source, self.content, self.output)
        self.assertEqual(self.source.read_bytes(), original)
        self.assertEqual(self.output.read_bytes(), b'keep-existing')


if __name__ == '__main__':
    unittest.main()
