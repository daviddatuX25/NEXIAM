#!/usr/bin/env python3
"""Inspect a manuscript DOCX or create a bounded formatting trial without round-tripping it.

Only document.xml, its relationships, and the new PNG are changed in trial mode.
The original template, sections, styles, TOC cache, and page furniture are preserved.
"""
import argparse
from copy import deepcopy
from hashlib import sha256
from io import BytesIO
import json
import subprocess
from pathlib import Path
from zipfile import ZipFile
from lxml import etree as ET
from PIL import Image

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
REL = 'http://schemas.openxmlformats.org/package/2006/relationships'
NS = {'w': W, 'r': R}
PARSER = ET.XMLParser(resolve_entities=False, no_network=True)


def xml(data):
    return ET.fromstring(data, PARSER)


def node(name, **attrs):
    return ET.Element(f'{{{W}}}{name}', {f'{{{W}}}{k}': str(v) for k, v in attrs.items()})


def text(element):
    return ''.join(element.xpath('.//w:t/text()', namespaces=NS))


def signature(element):
    return ET.tostring(element, method='c14n')


def describe(element):
    return [{'tag': ET.QName(e).localname,
             'attributes': {ET.QName(k).localname: v for k, v in e.attrib.items()},
             **({'text': e.text} if e.text and e.text.strip() else {})}
            for e in element.iter()]


def package(path):
    with ZipFile(path) as archive:
        if archive.testzip():
            raise ValueError('Corrupt ZIP member')
        return {name: archive.read(name) for name in archive.namelist()}


def inventory(path):
    parts = package(path)
    document = xml(parts['word/document.xml'])
    styles = xml(parts['word/styles.xml'])
    important = {'Normal', 'Standard', 'Textbody', 'Heading1', 'Heading2', 'Heading3',
                 'Caption', 'TOC1', 'TOC2', 'TOC3', 'TableofFigures',
                 'CASPTONESUBHEADING', 'CAPSTONEHEADING', 'CAPSTONENORMAL', 'Default'}
    toc = document.xpath('//w:p[w:pPr/w:pStyle[starts-with(@w:val,"TOC")]]', namespaces=NS)
    return {
        'source': str(path), 'sha256': sha256(Path(path).read_bytes()).hexdigest(),
        'sections': [describe(s) for s in document.findall('.//w:sectPr', NS)],
        'styles': {s.get(f'{{{W}}}styleId'): describe(s)
                   for s in styles.findall('w:style', NS)
                   if s.get(f'{{{W}}}styleId') in important},
        'toc_paragraphs': [{'text': text(p), 'properties': describe(p.find('w:pPr', NS))}
                           for p in toc],
        'fields': document.xpath('//w:instrText/text()', namespaces=NS),
        'captions': [{'text': text(p), 'properties': describe(p.find('w:pPr', NS)),
                      'run_properties': [describe(e) for e in p.findall('w:r/w:rPr', NS)]}
                     for p in document.xpath('//w:p[w:pPr/w:pStyle[@w:val="Caption"]]', namespaces=NS)],
        'tables': [{'index': i, 'rows': len(t.findall('w:tr', NS)),
                    'text': text(t), 'properties': describe(t.find('w:tblPr', NS)),
                    'cell_borders': [describe(b) for b in t.findall('.//w:tcBorders', NS)]}
                   for i, t in enumerate(document.findall('.//w:tbl', NS))],
        'page_furniture': {name: {'sha256': sha256(data).hexdigest(),
                                  'fields': xml(data).xpath('//w:instrText/text()', namespaces=NS)}
                           for name, data in parts.items()
                           if name.startswith(('word/header', 'word/footer')) and name.endswith('.xml')},
        'part_hashes': {name: sha256(data).hexdigest() for name, data in parts.items()},
    }


def three_line_table(table):
    """Override inherited borders explicitly; retain the template's 18/8 pt rule weight."""
    properties = table.find('w:tblPr', NS)
    borders = properties.find('w:tblBorders', NS)
    if borders is not None:
        properties.remove(borders)
    borders = node('tblBorders')
    # tblBorders belongs before layout, margins, look, and shading in CT_TblPr.
    before = next((e for e in properties if ET.QName(e).localname in
                   {'shd', 'tblLayout', 'tblCellMar', 'tblLook', 'tblPrChange'}), None)
    if before is None:
        properties.append(borders)
    else:
        properties.insert(properties.index(before), borders)
    for edge in ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']:
        borders.append(node(edge, val='nil'))
    rows = table.findall('w:tr', NS)
    for index, row in enumerate(rows):
        for cell in row.findall('w:tc', NS):
            cp = cell.find('w:tcPr', NS)
            if cp is None:
                cp = node('tcPr')
                cell.insert(0, cp)
            for old in cp.findall('w:tcBorders', NS):
                cp.remove(old)
            cb = node('tcBorders')
            before = next((e for e in cp if ET.QName(e).localname in
                           {'shd', 'noWrap', 'tcMar', 'textDirection', 'tcFitText', 'vAlign',
                            'hideMark', 'headers', 'cellIns', 'cellDel', 'cellMerge', 'tcPrChange'}), None)
            if before is None:
                cp.append(cb)
            else:
                cp.insert(cp.index(before), cb)
            for edge in ['top', 'left', 'bottom', 'right', 'insideH', 'insideV', 'tl2br', 'tr2bl']:
                required = (index == 0 and edge in {'top', 'bottom'}) or (index == len(rows)-1 and edge == 'bottom')
                cb.append(node(edge, val='single', sz='18', space='0', color='auto')
                          if required else node(edge, val='nil'))


def paragraph(value, style):
    p = node('p')
    pr = node('pPr')
    pr.append(node('pStyle', val=style))
    p.append(pr)
    run = node('r')
    t = node('t')
    t.text = value
    run.append(t)
    p.append(run)
    return p


def trial(source, output, image):
    source, output = Path(source).resolve(), Path(output).resolve()
    if source == output or output.exists():
        raise ValueError('Use a new output path; source and existing outputs are never overwritten')
    parts = package(source)
    original = xml(parts['word/document.xml'])
    document = deepcopy(original)
    body = document.find('w:body', NS)
    tables = document.findall('.//w:tbl', NS)
    expected = ['June 2026', 'RoleNameFunction', 'Respondentsn']
    if len(tables) != 3 or any(not text(t).startswith(s) for t, s in zip(tables, expected)):
        raise ValueError('Template table identity changed; inspect and explicitly remap before patching')
    # Index zero is the editable project-plan Figure, not an ordinary table.
    for table in tables[1:]:
        three_line_table(table)
    image_bytes = Path(image).read_bytes()
    if image_bytes[:8] != b'\x89PNG\r\n\x1a\n':
        raise ValueError('The trial expects a PNG diagram')
    with Image.open(BytesIO(image_bytes)) as diagram:
        diagram.verify()
        width, height = diagram.size
    types = xml(parts['[Content_Types].xml'])
    if not types.xpath('//*[local-name()="Default" and @Extension="png" and @ContentType="image/png"]'):
        raise ValueError('Template lacks PNG content type; stop rather than alter package assumptions')
    rels = xml(parts['word/_rels/document.xml.rels'])
    used_ids = {r.get('Id') for r in rels}
    rid = next(f'rId{i}' for i in range(1, len(used_ids)+2) if f'rId{i}' not in used_ids)
    media = 'word/media/nexiam-engineering-trial.png'
    if media in parts:
        raise ValueError('Trial media already exists')
    ET.SubElement(rels, f'{{{REL}}}Relationship', Id=rid, Type=f'{R}/image',
                  Target='media/nexiam-engineering-trial.png')
    section = body.find('w:sectPr', NS)
    page = section.find('w:pgSz', NS)
    margins = section.find('w:pgMar', NS)
    usable = int(page.get(f'{{{W}}}w')) - sum(int(margins.get(f'{{{W}}}{k}')) for k in ['left', 'right', 'gutter'])
    # Reserve room for subtitle, paragraph, caption and page furniture; never distort.
    scale = min(usable * 635 / width, 6.0 * 914400 / height)
    cx, cy = round(width * scale), round(height * scale)
    heading = paragraph('DOCX Engineering Trial — Diagram Placement', 'Heading3')
    heading.find('w:pPr', NS).append(node('keepNext'))
    heading.find('w:pPr', NS).append(node('pageBreakBefore'))
    prose = paragraph('This formatting trial shows the evidence-to-process mapping used in the manuscript. '
                      'The diagram remains centered within the template margins, with an automatically numbered '
                      'Figure caption beneath it. This page is an engineering sample, not an additional research finding.', 'Textbody')
    prose.find('w:pPr', NS).append(node('keepNext'))
    drawing_p = node('p')
    pp = node('pPr')
    pp.append(node('keepNext'))
    pp.append(node('spacing', before='0', after='0', line='240', lineRule='auto'))
    pp.append(node('jc', val='center'))
    drawing_p.append(pp)
    run = node('r')
    drawing_p.append(run)
    drawing = node('drawing')
    run.append(drawing)
    ids = document.xpath('//*[local-name()="docPr"]/@id')
    drawing_id = max([int(i) for i in ids] + [0]) + 1
    # Inline placement behaves as a centered character, avoiding floating-object collisions.
    inline = ET.fromstring(f'''<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:r="{R}" distT="0" distB="0" distL="0" distR="0">
      <wp:extent cx="{cx}" cy="{cy}"/><wp:docPr id="{drawing_id}" name="Engineering trial diagram" descr="Evidence sources reviewed and mapped to reported workflows, concerns and proposed requirements."/>
      <wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>
      <pic:nvPicPr><pic:cNvPr id="0" name="nexiam-engineering-trial.png"/><pic:cNvPicPr/></pic:nvPicPr>
      <pic:blipFill><a:blip r:embed="{rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
      <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{cx}" cy="{cy}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>
      </pic:pic></a:graphicData></a:graphic></wp:inline>''', PARSER)
    drawing.append(inline)
    sample = next(p for p in document.xpath('//w:p[w:pPr/w:pStyle[@w:val="Caption"]]', namespaces=NS)
                  if text(p).startswith('Figure 3.'))
    caption = node('p')
    caption.append(deepcopy(sample.find('w:pPr', NS)))
    run_pr = sample.find('w:r/w:rPr', NS)
    def append_run(value=None, child=None):
        r = node('r')
        if run_pr is not None:
            r.append(deepcopy(run_pr))
        if value is not None:
            t = node('t')
            t.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
            t.text = value
            r.append(t)
        if child is not None:
            r.append(child)
        caption.append(r)
    append_run('Figure ')
    append_run(child=node('fldChar', fldCharType='begin'))
    instr = node('instrText')
    instr.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
    instr.text = ' SEQ Figure \\* ARABIC '
    append_run(child=instr)
    append_run(child=node('fldChar', fldCharType='separate'))
    append_run('4')
    append_run(child=node('fldChar', fldCharType='end'))
    append_run('. Evidence-to-Process Mapping (Formatting Trial)')
    for element in [heading, prose, drawing_p, caption]:
        body.insert(body.index(section), element)
    parts['word/document.xml'] = ET.tostring(document, xml_declaration=True, encoding='UTF-8', standalone=True)
    parts['word/_rels/document.xml.rels'] = ET.tostring(rels, xml_declaration=True, encoding='UTF-8', standalone=True)
    parts[media] = image_bytes
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(source) as original_zip, ZipFile(output, 'x') as dest:
        for info in original_zip.infolist():
            dest.writestr(info, parts[info.filename])
        dest.writestr(media, image_bytes)
    return validate(source, output)


def validate(source, output):
    old, new = package(source), package(output)
    a, b = xml(old['word/document.xml']), xml(new['word/document.xml'])
    changed = [n for n in old if old[n] != new.get(n)]
    added = sorted(set(new) - set(old))
    assert set(changed) == {'word/document.xml', 'word/_rels/document.xml.rels'}, changed
    assert added == ['word/media/nexiam-engineering-trial.png'], added
    assert [signature(s) for s in a.findall('.//w:sectPr', NS)] == [signature(s) for s in b.findall('.//w:sectPr', NS)]
    assert signature(a.findall('.//w:tbl', NS)[0]) == signature(b.findall('.//w:tbl', NS)[0])
    toc_path = '//w:p[w:pPr/w:pStyle[starts-with(@w:val,"TOC")]]'
    assert [signature(p) for p in a.xpath(toc_path, namespaces=NS)] == [signature(p) for p in b.xpath(toc_path, namespaces=NS)]
    # Original visible text, including text boxes, remains in the original order.
    original_text = a.xpath('//w:t/text()', namespaces=NS)
    trial_text = b.xpath('//w:t/text()', namespaces=NS)
    assert trial_text[:len(original_text)] == original_text
    for table in b.findall('.//w:tbl', NS)[1:]:
        rows = table.findall('w:tr', NS)
        for i, row in enumerate(rows):
            for cell in row.findall('w:tc', NS):
                for border in cell.findall('w:tcPr/w:tcBorders/*', NS):
                    edge = ET.QName(border).localname
                    required = (i == 0 and edge in {'top', 'bottom'}) or (i == len(rows)-1 and edge == 'bottom')
                    assert border.get(f'{{{W}}}val') == ('single' if required else 'nil')
    return {'source_sha256': sha256(Path(source).read_bytes()).hexdigest(),
            'trial_sha256': sha256(Path(output).read_bytes()).hexdigest(),
            'changed_parts': changed, 'added_parts': added,
            'preserved': ['all pre-existing visible text; new trial content appended', 'all styles', 'all TOC cached paragraphs',
                          'both section properties', 'all headers and footers', 'project-plan Figure table',
                          'all original media', 'all original caption fields'],
            'verification': 'ZIP/XML structure and preservation checks passed; visual rendering is a separate gate'}


def repair_ipo(source, output):
    source, output = Path(source).resolve(), Path(output).resolve()
    if source == output or output.exists():
        raise ValueError('Use a new output path; source and existing outputs are never overwritten')
    parts = package(source)
    document = xml(parts['word/document.xml'])
    original_text = document.xpath('//w:t/text()', namespaces=NS)
    body = document.find('w:body', NS)
    ns = {'mc': 'http://schemas.openxmlformats.org/markup-compatibility/2006',
          'wp': 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
          'a': 'http://schemas.openxmlformats.org/drawingml/2006/main',
          'wpg': 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup'}
    ipo = next((ac for ac in document.xpath('//mc:AlternateContent', namespaces=ns)
                if all(term in ''.join(ac.xpath('.//w:t/text()', namespaces=NS))
                       for term in ('Input', 'Process', 'Output'))), None)
    if ipo is None:
        raise ValueError('Could not identify the editable IPO AlternateContent')
    choice = ipo.find('{%s}Choice' % ns['mc'])
    anchor = choice.find('.//{%s}anchor' % ns['wp'])
    if anchor is None:
        raise ValueError('IPO modern drawing anchor is missing')
    graphic = anchor.find('.//{%s}graphic' % ns['a'])
    group = graphic.find('.//{%s}wgp' % ns['wpg'])
    if group is None:
        raise ValueError('IPO modern editable group is missing')
    host = ipo.xpath('ancestor::w:p[1]', namespaces=NS)[0]
    ipo.getparent().remove(ipo)
    output_paragraph = next((p for p in body.findall('w:p', NS)
                             if 'Admission support models' in text(p)), host)
    specimen = next((p for p in document.xpath('//w:p[w:pPr/w:pStyle[@w:val="Caption"]]', namespaces=NS)
                     if text(p).startswith('Figure 3.')), None)
    if specimen is None:
        raise ValueError('Effective Figure 3 Caption specimen is missing')
    caption = deepcopy(specimen)
    for child in list(caption):
        if child.tag != f'{{{W}}}pPr':
            caption.remove(child)
    cap_style = caption.find('w:pPr/w:pStyle', NS)
    caption.find('w:pPr', NS).insert(
        caption.find('w:pPr', NS).index(cap_style) + 1 if cap_style is not None else 0,
        node('keepNext'))
    def add_run(value=None, field=None):
        run = node('r')
        rpr = specimen.find('w:r/w:rPr', NS)
        if rpr is not None:
            run.append(deepcopy(rpr))
        if field:
            run.append(node('fldChar', fldCharType=field))
        elif value is not None:
            t = node('t')
            t.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
            t.text = value
            run.append(t)
        caption.append(run)
    add_run('Figure ')
    add_run(field='begin')
    add_run()
    instr = node('instrText')
    instr.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
    instr.text = ' SEQ Figure \\* ARABIC '
    caption[-1].append(instr)
    add_run(field='separate'); add_run('1'); add_run(field='end')
    add_run('. IPO Diagram')
    cloned_group = deepcopy(group)
    for caption_text_node in cloned_group.xpath('.//w:t[contains(., "IPO Diagram")]', namespaces=NS):
        shape = caption_text_node.xpath('ancestor::*[local-name()="wsp"][1]')
        if shape:
            shape[0].getparent().remove(shape[0])
            break
    section = body.find('w:sectPr', NS)
    page, margins = section.find('w:pgSz', NS), section.find('w:pgMar', NS)
    cx = (int(page.get(f'{{{W}}}w')) -
          sum(int(margins.get(f'{{{W}}}{k}')) for k in ('left', 'right', 'gutter'))) * 635
    cy = round(5.4 * 914400)
    inline = ET.Element('{%s}inline' % ns['wp'], {k: '0' for k in ('distT', 'distB', 'distL', 'distR')})
    extent = deepcopy(anchor.find('{%s}extent' % ns['wp']))
    extent.set('cx', str(cx)); extent.set('cy', str(cy)); inline.append(extent)
    effect = anchor.find('{%s}effectExtent' % ns['wp'])
    if effect is not None:
        inline.append(deepcopy(effect))
    docpr = deepcopy(anchor.find('{%s}docPr' % ns['wp']))
    docpr.set('id', str(max([int(i) for i in document.xpath('//*[local-name()="docPr"]/@id')] + [0]) + 1))
    inline.append(docpr)
    frame = anchor.find('{%s}cNvGraphicFramePr' % ns['wp'])
    if frame is not None:
        inline.append(deepcopy(frame))
    new_graphic = deepcopy(graphic)
    xfrm = cloned_group.find('.//{%s}grpSpPr/{%s}xfrm' % (ns['wpg'], ns['a']))
    ext = xfrm.find('{%s}ext' % ns['a']) if xfrm is not None else None
    if ext is not None:
        ext.set('cx', str(cx)); ext.set('cy', str(cy))
    data = new_graphic.find('.//{%s}graphicData' % ns['a'])
    for child in list(data):
        data.remove(child)
    data.append(cloned_group)
    inline.append(new_graphic)
    figure = node('p')
    ppr = node('pPr'); ppr.append(node('keepNext'))
    ppr.append(node('spacing', before='0', after='0', line='240', lineRule='auto'))
    ppr.append(node('jc', val='center')); figure.append(ppr)
    run = node('r'); figure.append(run)
    drawing = node('drawing'); run.append(drawing); drawing.append(inline)
    parent = output_paragraph.getparent()
    pos = parent.index(output_paragraph) + 1
    parent.insert(pos, caption); parent.insert(pos + 1, figure)
    objectives = next((p for p in body.findall('w:p', NS) if text(p).strip() == 'Objectives of the Study'), None)
    if objectives is None:
        raise ValueError('Objectives heading is missing')
    obj_pr = objectives.find('w:pPr', NS)
    if obj_pr is None:
        obj_pr = node('pPr'); objectives.insert(0, obj_pr)
    style = obj_pr.find('w:pStyle', NS)
    obj_pr.insert(obj_pr.index(style) + 1 if style is not None else 0, node('keepNext'))
    objective_index = body.index(objectives)
    opening = body[objective_index + 1]
    if opening.tag != f'{{{W}}}p':
        raise ValueError('Objectives opening paragraph is not a paragraph')
    open_pr = opening.find('w:pPr', NS)
    if open_pr is None:
        open_pr = node('pPr'); opening.insert(0, open_pr)
    style = open_pr.find('w:pStyle', NS)
    open_pr.insert(open_pr.index(style) + 1 if style is not None else 0, node('keepLines'))
    parts['word/document.xml'] = ET.tostring(document, xml_declaration=True, encoding='UTF-8', standalone=True)
    with ZipFile(source) as src, ZipFile(output, 'x') as dest:
        for info in src.infolist():
            dest.writestr(info, parts[info.filename])
    before = package(source)
    after = package(output)
    changed = [name for name in before if before[name] != after.get(name)]
    if changed != ['word/document.xml']:
        raise ValueError(f'Unexpected package parts changed: {changed}')
    return {'source_sha256': sha256(Path(source).read_bytes()).hexdigest(),
            'output_sha256': sha256(Path(output).read_bytes()).hexdigest(),
            'changed_parts': changed, 'preserved_parts_byte_identical': len(before) - 1,
            'source_text_count': len(original_text), 'ipo_text': text(cloned_group),
            'caption': text(caption), 'page_width_emu': cx, 'diagram_height_emu': cy}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    inspect = commands.add_parser('inspect')
    inspect.add_argument('source', type=Path)
    create = commands.add_parser('trial')
    create.add_argument('source', type=Path)
    create.add_argument('output', type=Path)
    create.add_argument('--diagram', type=Path, required=True)
    check = commands.add_parser('validate')
    check.add_argument('source', type=Path)
    check.add_argument('output', type=Path)
    repair = commands.add_parser('repair-ipo')
    repair.add_argument('source', type=Path)
    repair.add_argument('output', type=Path)
    report = commands.add_parser('report-ipo')
    report.add_argument('source', type=Path)
    report.add_argument('output', type=Path)
    report.add_argument('pdf', type=Path)
    report.add_argument('report', type=Path)
    args = parser.parse_args()
    if args.command == 'inspect':
        result = inventory(args.source)
    elif args.command == 'trial':
        result = trial(args.source, args.output, args.diagram)
    elif args.command == 'validate':
        result = validate(args.source, args.output)
    elif args.command == 'repair-ipo':
        result = repair_ipo(args.source, args.output)
    else:
        source_parts, output_parts = package(args.source), package(args.output)
        source_doc, output_doc = xml(source_parts['word/document.xml']), xml(output_parts['word/document.xml'])
        assert set(source_parts) == set(output_parts), 'ZIP package member list changed'
        changed = [name for name in source_parts if source_parts[name] != output_parts.get(name)]
        assert changed == ['word/document.xml'], changed
        ns = {'wp': 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
              'wpg': 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup',
              'a': 'http://schemas.openxmlformats.org/drawingml/2006/main',
              'mc': 'http://schemas.openxmlformats.org/markup-compatibility/2006'}
        old_ipo = next(ac for ac in source_doc.xpath('//mc:AlternateContent', namespaces=ns)
                       if all(term in ''.join(ac.xpath('.//w:t/text()', namespaces=NS))
                              for term in ('Input', 'Process', 'Output')))
        groups = output_doc.xpath('//wp:inline//wpg:wgp', namespaces=ns)
        assert len(groups) == 1, len(groups)
        captions = output_doc.xpath('//w:p[w:pPr/w:pStyle[@w:val="Caption"]]', namespaces=NS)
        ipo_caption = next(p for p in captions if 'IPO Diagram' in text(p))
        original_text = [t.text or '' for t in source_doc.xpath('//w:t', namespaces=NS)
                         if not old_ipo in t.xpath('ancestor::mc:AlternateContent', namespaces=ns)]
        output_text = [t.text or '' for t in output_doc.xpath('//w:t', namespaces=NS)
                       if not groups[0] in t.iterancestors() and ipo_caption not in t.iterancestors()]
        assert ''.join(original_text) == ''.join(output_text), 'Original prose changed outside the removed duplicate IPO branches'
        group_text = ''.join(groups[0].xpath('.//w:t/text()', namespaces=NS))
        assert all(token in group_text for token in ('Input', 'Process', 'Output', 'Admission support models', 'Course slots monitoring'))
        right_arrows = len(groups[0].xpath('.//a:prstGeom[@prst="rightArrow"]', namespaces=ns))
        arrow_media = len(groups[0].xpath('.//*[local-name()="pic"]', namespaces=ns))
        arrows = right_arrows + arrow_media
        assert arrows == 2, (right_arrows, arrow_media)
        pagination = {'w': W}
        heading = next(p for p in output_doc.xpath('//w:p', namespaces=NS)
                       if text(p).strip() == 'Objectives of the Study')
        opening = heading.getnext()
        assert heading.find('w:pPr/w:keepNext', pagination) is not None
        assert opening.find('w:pPr/w:keepLines', pagination) is not None
        pdf_text = subprocess.run(['pdftotext', '-layout', str(args.pdf), '-'],
                                  check=True, capture_output=True, text=True).stdout
        pdf_pages = pdf_text.split('\f')
        ipo_page = next((i for i, page_text in enumerate(pdf_pages, 1)
                         if 'Figure 1. IPO Diagram' in page_text and 'Input' in page_text and 'Process' in page_text), None)
        assert ipo_page is not None
        page_text = pdf_pages[ipo_page - 1]
        for token in ('Input', 'Process', 'Output', 'Admission support', 'models',
                      'Course slots monitoring', 'Objectives of the Study',
                      'This study aims to develop NEXIAM:', 'result delivery.',
                      'Bachelor of Science in Information Technology'):
            assert token in page_text, f'IPO page text guard missing {token!r}'
        footer_at = page_text.find('Bachelor of Science in Information Technology')
        pdfinfo = subprocess.run(['pdfinfo', str(args.pdf)], check=True, capture_output=True, text=True)
        pages = int(next(line.split(':', 1)[1].strip() for line in pdfinfo.stdout.splitlines()
                         if line.startswith('Pages:')))
        assert page_text.find('Course slots monitoring') < footer_at, 'Bottom IPO label falls below footer in PDF text order'
        assert page_text.find('Objectives of the Study') < page_text.find('This study aims to develop')
        report_data = {'source': str(args.source), 'output': str(args.output), 'pdf': str(args.pdf),
                       'source_sha256': sha256(args.source.read_bytes()).hexdigest(),
                       'output_sha256': sha256(args.output.read_bytes()).hexdigest(),
                       'pdf_sha256': sha256(args.pdf.read_bytes()).hexdigest(),
                       'pdf_pages': pages, 'ipo_pdf_page': ipo_page, 'changed_parts': changed,
                       'other_parts_byte_identical': True, 'source_text_preserved_in_order': True,
                       'editable_native_group_count': 1, 'ipo_text': group_text, 'arrows': arrows,
                       'live_sequence_caption': True, 'caption_style_specimen': 'Figure 3 Caption',
                       'objectives_heading_keep_next': True, 'opening_paragraph_keep_lines': True,
                       'ipo_pdf_text_guard': 'passed; caption, all three headers, bottom labels, opening paragraph, and footer are present in order on IPO page',
                       'section_properties_identical': [signature(s) for s in source_doc.findall('.//w:sectPr', NS)] ==
                                                       [signature(s) for s in output_doc.findall('.//w:sectPr', NS)],
                       'limitations': ['PDF text order guard is not a pixel/bounding-box collision analysis.',
                                       'LibreOffice render only; Word/Google Docs native editor and XSD verification not performed.',
                                       'No research-claim validation implied.']}
        args.report.write_text(json.dumps(report_data, indent=2, ensure_ascii=False) + '\n')
        result = report_data
    print(json.dumps(result, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
