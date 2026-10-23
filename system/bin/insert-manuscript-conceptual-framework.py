#!/usr/bin/env python3
"""Insert current conceptual-framework prose while retaining the native IPO group and caption."""
import argparse
from copy import deepcopy
from hashlib import sha256
import json
from pathlib import Path
import re
from zipfile import ZipFile

from lxml import etree as ET

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing'
WPG = 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup'
A = 'http://schemas.openxmlformats.org/drawingml/2006/main'
NS = {'w': W, 'wp': WP, 'wpg': WPG, 'a': A}
XML_SPACE = '{http://www.w3.org/XML/1998/namespace}space'
PARSER = ET.XMLParser(resolve_entities=False, no_network=True)


def text(element):
    return ''.join(element.xpath('.//w:t/text()', namespaces=NS))


def canonical(element):
    return ET.tostring(element, method='c14n', exclusive=True)


def keep_with_next(paragraph):
    properties = paragraph.find('w:pPr', NS)
    if properties is None:
        properties = ET.Element(f'{{{W}}}pPr')
        paragraph.insert(0, properties)
    if properties.find('w:keepNext', NS) is None:
        p_style = properties.find('w:pStyle', NS)
        properties.insert(properties.index(p_style) + 1 if p_style is not None else 0,
                          ET.Element(f'{{{W}}}keepNext'))


def source_paragraphs(markdown):
    lines = markdown.strip().splitlines()
    heading_lines = [(i, line.strip()) for i, line in enumerate(lines) if line.strip()][:2]
    if [line for _, line in heading_lines] != ['# Chapter 1: Introduction', '## 1.2 Conceptual Framework of the Study']:
        raise ValueError('Expected the current Chapter 1 / Conceptual Framework source headings')
    lines = lines[heading_lines[-1][0] + 1:]
    paragraphs = []
    current = []
    mermaid_blocks = 0
    captions = 0
    in_fence = False
    for line in lines:
        if line.startswith('```'):
            if in_fence:
                in_fence = False
                mermaid_blocks += 1
            else:
                if current:
                    paragraphs.append('\n'.join(current).strip())
                    current = []
                if line != '```mermaid':
                    raise ValueError('Unexpected fenced block in conceptual source')
                in_fence = True
            continue
        if in_fence:
            continue
        if not line.strip():
            if current:
                paragraphs.append('\n'.join(current).strip())
                current = []
            continue
        if line.strip() == '**Figure 1. IPO Diagram**':
            if current:
                paragraphs.append('\n'.join(current).strip())
                current = []
            captions += 1
            continue
        current.append(line)
    if in_fence:
        raise ValueError('Unclosed Mermaid source block')
    if current:
        paragraphs.append('\n'.join(current).strip())
    if len(paragraphs) != 1 or mermaid_blocks != 1 or captions != 1:
        raise ValueError('Expected one concise prose paragraph, one retained Mermaid block and one Figure 1 caption')
    for paragraph in paragraphs:
        if paragraph.count('**') % 2 or paragraph.count('$') % 2:
            raise ValueError('Unbalanced emphasis or math delimiters in conceptual prose')
        if '$' in paragraph:
            raise ValueError('Unmapped math in conceptual source; use the formula transfer register')
        if any(line.lstrip().startswith(('```', '|', '![')) for line in paragraph.splitlines()):
            raise ValueError('Unexpected diagram, table, or fenced content in prose')
    return paragraphs


def body_paragraph(value, paragraph_properties, run_properties):
    paragraph = ET.Element(f'{{{W}}}p')
    paragraph.append(deepcopy(paragraph_properties))

    def append_run(content, bold=False):
        if not content:
            return
        run = ET.SubElement(paragraph, f'{{{W}}}r')
        properties = deepcopy(run_properties) if run_properties is not None else ET.Element(f'{{{W}}}rPr')
        if bold:
            flag = properties.find(f'{{{W}}}b')
            if flag is None:
                flag = ET.SubElement(properties, f'{{{W}}}b')
            flag.set(f'{{{W}}}val', '1')
        if len(properties):
            run.append(properties)
        t = ET.SubElement(run, f'{{{W}}}t')
        t.set(XML_SPACE, 'preserve')
        t.text = content

    parts = re.split(r'(\*\*[^*]+\*\*)', value)
    for part in parts:
        if part.startswith('**') and part.endswith('**'):
            append_run(part[2:-2], bold=True)
        else:
            append_run(part)
    return paragraph


def insert_conceptual_framework(source, content_source, output):
    source, content_source, output = map(lambda p: Path(p).resolve(), [source, content_source, output])
    if output == source or output.exists():
        raise ValueError('Use a fresh output path; original and existing outputs are never overwritten')
    paragraphs = source_paragraphs(content_source.read_text(encoding='utf-8'))
    with ZipFile(source) as archive:
        if archive.testzip():
            raise ValueError('Source package is corrupt')
        parts = {name: archive.read(name) for name in archive.namelist()}
    document = ET.fromstring(parts['word/document.xml'], PARSER)
    body = document.find('w:body', NS)
    headings = [(i, p) for i, p in enumerate(body)
                if p.find('w:pPr/w:pStyle[@w:val="Heading3"]', NS) is not None]
    starts = [i for i, p in headings if text(p) == 'Conceptual Framework of the Study']
    if len(starts) != 1:
        raise ValueError('Conceptual Framework heading is missing or ambiguous')
    start = starts[0]
    following = [(i, p) for i, p in headings if i > start]
    if not following or text(following[0][1]) != 'Objectives of the Study':
        raise ValueError('Next section is not the expected Objectives heading')
    end = following[0][0]
    candidates = list(body)[start + 1:end]
    prose = [p for p in candidates if p.tag == f'{{{W}}}p' and text(p).strip()
             and p.find('w:pPr/w:pStyle[@w:val="Caption"]', NS) is None
             and p.find('.//w:drawing', NS) is None]
    captions = [p for p in candidates if p.find('w:pPr/w:pStyle[@w:val="Caption"]', NS) is not None]
    drawings = [p for p in candidates if p.find('.//w:drawing', NS) is not None]
    section_heading = body[start]
    if len(prose) not in {3, 4} or len(captions) != 1 or text(captions[0]) != 'Figure 1. IPO Diagram' or len(drawings) != 1:
        raise ValueError('Expected the prior prose block, unique Figure 1 caption, and one native IPO drawing')
    if body.index(captions[0]) >= body.index(drawings[0]):
        raise ValueError('IPO Figure caption must precede the IPO group')
    protected_tags = ['sectPr', 'fldChar', 'instrText', 'bookmarkStart', 'bookmarkEnd',
                      'footnoteReference', 'endnoteReference', 'commentRangeStart']
    if any(p.find(f'.//w:{tag}', NS) is not None for p in prose for tag in protected_tags):
        raise ValueError('A prose paragraph contains protected structure; re-map the cut')
    objective_heading = following[0][1]
    original_outside = [canonical(p) for p in body
                        if p not in prose and p not in [section_heading, objective_heading, captions[0], drawings[0]]]
    body_specimens = [p for p in list(body)[:start]
                      if p.find('w:pPr/w:pStyle[@w:val="Textbody"]', NS) is not None and text(p).strip()]
    if not body_specimens:
        raise ValueError('Verified Textbody paragraph specimen was not found before section')
    paragraph_properties = body_specimens[-1].find('w:pPr', NS)
    run_properties = body_specimens[-1].find('w:r/w:rPr', NS)
    inserted = [body_paragraph(value, paragraph_properties, run_properties) for value in paragraphs]
    group_inline = drawings[0].find('.//wp:inline', NS)
    group = drawings[0].find('.//wpg:wgp', NS)
    image_extent = group_inline.find('wp:extent', NS) if group_inline is not None else None
    group_transform = group.find('wpg:grpSpPr/a:xfrm', NS) if group is not None else None
    group_extent = group_transform.find('a:ext', NS) if group_transform is not None else None
    child_extent = group_transform.find('a:chExt', NS) if group_transform is not None else None
    if image_extent is None or group_extent is None or child_extent is None:
        raise ValueError('IPO is not the expected native editable DrawingML inline group')
    width = int(image_extent.get('cx'))
    coordinate_width, coordinate_height = int(child_extent.get('cx')), int(child_extent.get('cy'))
    if not width or not coordinate_width or not coordinate_height:
        raise ValueError('IPO group dimensions must be positive')
    fitted_height = round(width * coordinate_height / coordinate_width)
    expected_heading, expected_objectives, expected_caption, expected_drawing = map(
        deepcopy, [section_heading, objective_heading, captions[0], drawings[0]],
    )
    def fit_group(paragraph):
        inline = paragraph.find('.//wp:inline', NS)
        native_group = paragraph.find('.//wpg:wgp', NS)
        inline_extent = inline.find('wp:extent', NS)
        transform = native_group.find('wpg:grpSpPr/a:xfrm', NS)
        transform.find('a:ext', NS).set('cy', str(fitted_height))
        inline_extent.set('cy', str(fitted_height))
    fit_group(drawings[0])
    fit_group(expected_drawing)
    drawing_properties = drawings[0].find('w:pPr', NS)
    expected_drawing_properties = expected_drawing.find('w:pPr', NS)
    for properties in [drawing_properties, expected_drawing_properties]:
        if properties is not None:
            figure_keep_next = properties.find('w:keepNext', NS)
            if figure_keep_next is not None:
                properties.remove(figure_keep_next)
    for paragraph in [section_heading, inserted[0], captions[0], objective_heading]:
        keep_with_next(paragraph)
    keep_with_next(expected_heading)
    keep_with_next(expected_objectives)
    keep_with_next(expected_caption)
    insertion_index = body.index(prose[0])
    for old in prose:
        body.remove(old)
    for offset, new in enumerate(inserted):
        body.insert(insertion_index + offset, new)
    expected = [value.replace('**', '') for value in paragraphs]
    if [text(p) for p in inserted] != expected:
        raise ValueError('Inserted conceptual prose does not preserve source text')
    unchanged = [canonical(p) for p in body
                 if p not in inserted and p not in [section_heading, objective_heading, captions[0], drawings[0]]]
    if unchanged != original_outside:
        raise ValueError('Protected body content outside the conceptual section changed')
    if (canonical(section_heading) != canonical(expected_heading)
            or canonical(objective_heading) != canonical(expected_objectives)
            or canonical(captions[0]) != canonical(expected_caption)):
        raise ValueError('Heading/caption changed beyond local keep-with-next pagination')
    if canonical(drawings[0]) != canonical(expected_drawing):
        raise ValueError('IPO diagram changed beyond proportional height fitting')
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
        'section': '1.2 Conceptual Framework of the Study',
        'old_paragraphs_replaced': len(prose), 'current_paragraphs_inserted': len(inserted),
        'retained_mermaid_source_block': 1, 'retained_native_ipo_group': len(drawings),
        'retained_ipo_caption': text(captions[0]), 'changed_package_parts': changed,
        'protected_ipos_caption_drawing_and_other_body_content_preserved': True,
        'all_non_document_parts_byte_identical': True,
        'description_word_count': len(paragraphs[0].split()),
        'ipo_dimensions_emu': {'width': width, 'height': fitted_height, 'aspect_ratio_preserved': True},
        'heading_description_caption_keep_next': True,
        'figure_paragraph_keep_next_removed': True,
        'representation_only_changes': ['Four prior prose paragraphs condensed into one source-supported summary.', 'IPO group proportionally reduced to its original aspect ratio to fit description and diagram on one page.', 'Objectives heading kept with its opening paragraph on the next page.'],
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
    report = insert_conceptual_framework(args.source, args.content_source, args.output)
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        with args.report.open('x', encoding='utf-8') as file:
            json.dump(report, file, indent=2, ensure_ascii=False)
            file.write('\n')
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
