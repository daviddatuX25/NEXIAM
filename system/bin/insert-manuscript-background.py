#!/usr/bin/env python3
"""Insert the approved Background prose into a fresh, template-preserving DOCX copy."""
import argparse
from copy import deepcopy
from hashlib import sha256
import json
from pathlib import Path
import re
from zipfile import ZipFile

from lxml import etree as ET

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
NS = {'w': W}
XML_SPACE = '{http://www.w3.org/XML/1998/namespace}space'
PARSER = ET.XMLParser(resolve_entities=False, no_network=True)


def text(element):
    return ''.join(element.xpath('.//w:t/text()', namespaces=NS))


def canonical(element):
    return ET.tostring(element, method='c14n')


def source_paragraphs(markdown):
    blocks = [block.strip() for block in markdown.strip().split('\n\n') if block.strip()]
    if blocks[:2] != ['# Chapter 1: Introduction', '## 1.1 Background of the Study']:
        raise ValueError('Expected the current Chapter 1 / Background source headings')
    paragraphs = blocks[2:]
    if len(paragraphs) != 9 or any(block.startswith(('#', '```', '|', '![')) for block in paragraphs):
        raise ValueError('Expected nine prose paragraphs; re-map revised source before insertion')
    for paragraph in paragraphs:
        if paragraph.count('$') % 2 or '**' in paragraph:
            raise ValueError('Unmapped inline markup in Background source')
        for equation in re.findall(r'\$([^$]+)\$', paragraph):
            if not re.fullmatch(r'[A-Za-z]\s*=\s*[-+]?\d+(?:\.\d+)?', equation):
                raise ValueError(f'Equation needs an explicit Word transfer mapping: {equation}')
    return paragraphs


def body_paragraph(value, paragraph_properties, run_properties):
    paragraph = ET.Element(f'{{{W}}}p')
    paragraph.append(deepcopy(paragraph_properties))

    def append_run(content, variable=False):
        if not content:
            return
        run = ET.SubElement(paragraph, f'{{{W}}}r')
        properties = deepcopy(run_properties) if run_properties is not None else ET.Element(f'{{{W}}}rPr')
        if variable:
            for tag in ['i', 'iCs']:
                existing = properties.find(f'{{{W}}}{tag}')
                if existing is None:
                    existing = ET.SubElement(properties, f'{{{W}}}{tag}')
                existing.set(f'{{{W}}}val', '1')
        if len(properties):
            run.append(properties)
        t = ET.SubElement(run, f'{{{W}}}t')
        t.set(XML_SPACE, 'preserve')
        t.text = content

    for part in re.split(r'(\$[^$]+\$)', value):
        if part.startswith('$') and part.endswith('$'):
            equation = part[1:-1]
            append_run(equation[0], variable=True)
            append_run(equation[1:])
        else:
            append_run(part)
    return paragraph


def insert_background(source, content_source, output):
    source, content_source, output = map(lambda p: Path(p).resolve(), [source, content_source, output])
    if output == source or output.exists():
        raise ValueError('Use a fresh output path; original and existing outputs are never overwritten')
    markdown = content_source.read_text(encoding='utf-8')
    paragraphs = source_paragraphs(markdown)
    with ZipFile(source) as archive:
        if archive.testzip():
            raise ValueError('Source package is corrupt')
        parts = {name: archive.read(name) for name in archive.namelist()}
    document = ET.fromstring(parts['word/document.xml'], PARSER)
    body = document.find('w:body', NS)
    if body is None:
        raise ValueError('Source document has no body')
    headings = [(i, p) for i, p in enumerate(body)
                if p.find('w:pPr/w:pStyle[@w:val="Heading3"]', NS) is not None]
    starts = [i for i, p in headings if text(p) == 'Background of the Study']
    if len(starts) != 1:
        raise ValueError('Background heading is missing or ambiguous')
    start = starts[0]
    following = [(i, p) for i, p in headings if i > start]
    if not following or text(following[0][1]) != 'Conceptual Framework of the Study':
        raise ValueError('Next section is not the expected Conceptual Framework heading')
    end = following[0][0]
    candidates = list(body)[start + 1:end]
    if any(p.tag != f'{{{W}}}p' for p in candidates):
        raise ValueError('Background boundary contains a non-paragraph object')
    replaced = [p for p in candidates if text(p).strip()]
    if len(replaced) != 11:
        raise ValueError('Expected eleven old prose paragraphs; source may already be migrated')
    protected_tags = ['drawing', 'pict', 'sectPr', 'fldChar', 'instrText', 'bookmarkStart',
                      'bookmarkEnd', 'footnoteReference', 'endnoteReference', 'commentRangeStart']
    if any(p.find(f'.//w:{tag}', NS) is not None for p in replaced for tag in protected_tags):
        raise ValueError('A replacement paragraph contains protected structure; re-map the cut')
    original_outside = [canonical(p) for p in body if p not in replaced]
    paragraph_properties = replaced[0].find('w:pPr', NS)
    if paragraph_properties is None or paragraph_properties.find('w:pStyle[@w:val="Textbody"]', NS) is None:
        raise ValueError('Body formatting specimen is not the verified Textbody style')
    run_properties = replaced[0].find('w:r/w:rPr', NS)
    insertion_index = body.index(replaced[0])
    inserted = [body_paragraph(value, paragraph_properties, run_properties) for value in paragraphs]
    for p in replaced:
        body.remove(p)
    for index, p in enumerate(inserted):
        body.insert(insertion_index + index, p)
    expected_text = [re.sub(r'\$([^$]+)\$', r'\1', value) for value in paragraphs]
    if [text(p) for p in inserted] != expected_text:
        raise ValueError('Inserted prose does not preserve the source text')
    if [canonical(p) for p in body if p not in inserted] != original_outside:
        raise ValueError('Protected body content outside the cut changed')
    output.parent.mkdir(parents=True, exist_ok=True)
    updated_xml = ET.tostring(document, xml_declaration=True, encoding='UTF-8', standalone=True)
    with ZipFile(source) as original_archive, ZipFile(output, 'x') as result:
        for info in original_archive.infolist():
            result.writestr(info, updated_xml if info.filename == 'word/document.xml' else parts[info.filename])
    with ZipFile(output) as result:
        changed = [name for name, data in parts.items() if result.read(name) != data]
        if result.testzip() or changed != ['word/document.xml'] or set(result.namelist()) != set(parts):
            raise ValueError('Output package preservation failed')
    return {
        'source_docx': str(source), 'source_docx_sha256': sha256(source.read_bytes()).hexdigest(),
        'content_source': str(content_source), 'content_sha256': sha256(content_source.read_bytes()).hexdigest(),
        'output_docx': str(output), 'output_sha256': sha256(output.read_bytes()).hexdigest(),
        'section': '1.1 Background of the Study', 'old_paragraphs_replaced': 11,
        'current_paragraphs_inserted': 9, 'changed_package_parts': changed,
        'outside_cut_body_preserved': True, 'all_non_document_parts_byte_identical': True,
        'source_text_preserved': True,
        'representation_only_changes': ['Inline dollar delimiters removed; the single-letter mathematical variable is italic Word text.'],
        'remaining_sections': 'Retained from input; not claimed migrated or research-validated.',
        'rendering_status': 'DOCX/package checks only; inspect the generated PDF before publication.',
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('content_source', type=Path)
    parser.add_argument('output', type=Path)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if args.report and args.report.exists():
        raise ValueError('Use a fresh report path')
    report = insert_background(args.source, args.content_source, args.output)
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        with args.report.open('x', encoding='utf-8') as file:
            json.dump(report, file, indent=2, ensure_ascii=False)
            file.write('\n')
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
