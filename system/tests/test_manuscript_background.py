"""Behavior checks for the bounded manuscript Background insertion command."""
import importlib.util
from pathlib import Path
import tempfile
import unittest
from zipfile import ZipFile

from lxml import etree as ET

SPEC = importlib.util.spec_from_file_location(
    'insert_manuscript_background', Path(__file__).resolve().parents[1] / 'bin' / 'insert-manuscript-background.py',
)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)
W = MODULE.W
NS = MODULE.NS


class BackgroundInsertionTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='nexiam-background-test-')
        self.addCleanup(self.scratch.cleanup)
        self.root = Path(self.scratch.name)
        self.source = self.root / 'source.docx'
        self.content = self.root / 'background.md'
        self.output = self.root / 'output.docx'
        self.prose = [f'Current source paragraph {index}.' for index in range(9)]
        self.prose[4] = 'Population ($N = 890$) is retained without recalculation.'
        self.content.write_text('# Chapter 1: Introduction\n\n## 1.1 Background of the Study\n\n' + '\n\n'.join(self.prose), encoding='utf-8')
        self.document = ET.Element(f'{{{W}}}document', nsmap={'w': W})
        self.body = ET.SubElement(self.document, f'{{{W}}}body')
        self.paragraph('Background of the Study', 'Heading3')
        self.old = [self.paragraph(f'Old source {index}.', 'Textbody') for index in range(11)]
        blank = ET.SubElement(self.body, f'{{{W}}}p')
        ET.SubElement(blank, f'{{{W}}}bookmarkStart', {f'{{{W}}}id': '18', f'{{{W}}}name': 'next-section'})
        next_heading = self.paragraph('Conceptual Framework of the Study', 'Heading3')
        ET.SubElement(next_heading, f'{{{W}}}bookmarkEnd', {f'{{{W}}}id': '18'})
        self.paragraph('Other section content remains unchanged.', 'Textbody')
        ET.SubElement(self.body, f'{{{W}}}sectPr')
        self.footer = b'<footer font="Edwardian Script ITC" size="16">Degree text</footer>'
        self.save_source()

    def paragraph(self, value, style):
        paragraph = ET.SubElement(self.body, f'{{{W}}}p')
        properties = ET.SubElement(paragraph, f'{{{W}}}pPr')
        ET.SubElement(properties, f'{{{W}}}pStyle', {f'{{{W}}}val': style})
        run = ET.SubElement(paragraph, f'{{{W}}}r')
        ET.SubElement(run, f'{{{W}}}t').text = value
        return paragraph

    def save_source(self):
        with ZipFile(self.source, 'w') as archive:
            archive.writestr('word/document.xml', ET.tostring(self.document))
            archive.writestr('word/footer1.xml', self.footer)

    def test_replaces_prose_preserving_next_section_bookmark_and_footer_font(self):
        original_bytes = self.source.read_bytes()
        MODULE.insert_background(self.source, self.content, self.output)
        with ZipFile(self.output) as archive:
            result = ET.fromstring(archive.read('word/document.xml'))
            self.assertEqual(archive.read('word/footer1.xml'), self.footer)
        body = result.find('w:body', NS)
        expected = [value.replace('$', '') for value in self.prose]
        self.assertEqual([MODULE.text(p) for p in list(body)[1:10]], expected)
        start = result.find('.//w:bookmarkStart[@w:id="18"]', NS)
        end = result.find('.//w:bookmarkEnd[@w:id="18"]', NS)
        self.assertEqual(MODULE.text(start.getparent()), '')
        self.assertEqual(MODULE.text(end.getparent()), 'Conceptual Framework of the Study')
        variable_run = next(r for r in result.findall('.//w:r', NS) if MODULE.text(r) == 'N')
        self.assertEqual(variable_run.find('w:rPr/w:i', NS).get(f'{{{W}}}val'), '1')
        self.assertIn('Other section content remains unchanged.', MODULE.text(result))
        self.assertEqual(self.source.read_bytes(), original_bytes)

    def test_refuses_to_remove_a_protected_bookmark_inside_prose(self):
        ET.SubElement(self.old[0], f'{{{W}}}bookmarkStart', {f'{{{W}}}id': '42', f'{{{W}}}name': 'protected-reference'})
        self.save_source()
        with self.assertRaisesRegex(ValueError, 'protected structure'):
            MODULE.insert_background(self.source, self.content, self.output)
        self.assertFalse(self.output.exists())

    def test_refuses_source_or_existing_output_overwrite(self):
        original_bytes = self.source.read_bytes()
        with self.assertRaisesRegex(ValueError, 'fresh output'):
            MODULE.insert_background(self.source, self.content, self.source)
        self.output.write_bytes(b'existing review file')
        with self.assertRaisesRegex(ValueError, 'fresh output'):
            MODULE.insert_background(self.source, self.content, self.output)
        self.assertEqual(self.output.read_bytes(), b'existing review file')
        self.assertEqual(self.source.read_bytes(), original_bytes)


if __name__ == '__main__':
    unittest.main()
