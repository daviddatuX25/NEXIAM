export const RESULT_SHEET_FONT_OPTIONS = [
  { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
  { value: 'Inter, Arial, sans-serif', label: 'Inter' },
  { value: 'Georgia, serif', label: 'Georgia' },
  { value: 'Times New Roman, serif', label: 'Times New Roman' },
  { value: 'Verdana, sans-serif', label: 'Verdana' },
];

export const RESULT_SHEET_TEXT_ALIGN_OPTIONS = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Center' },
  { value: 'right', label: 'Right' },
  { value: 'justify', label: 'Justify' },
];

export const RESULT_SHEET_BORDER_STYLE_OPTIONS = [
  { value: 'solid', label: 'Solid' },
  { value: 'dashed', label: 'Dashed' },
  { value: 'dotted', label: 'Dotted' },
];

const TEXT_PARTS_BY_TYPE = {
  header: [
    { value: 'title', label: 'Title' },
    { value: 'subtitle', label: 'Subtitle' },
  ],
  identity: [
    { value: 'label', label: 'Labels' },
    { value: 'value', label: 'Values' },
  ],
  exam_details: [{ value: 'body', label: 'Details' }],
  field: [
    { value: 'label', label: 'Label' },
    { value: 'value', label: 'Value' },
  ],
  score_table: [
    { value: 'title', label: 'Title' },
    { value: 'heading', label: 'Column headings' },
    { value: 'body', label: 'Rows' },
  ],
  data_table: [
    { value: 'title', label: 'Title' },
    { value: 'heading', label: 'Column headers' },
    { value: 'body', label: 'Data rows' },
  ],
  kpi_grid: [
    { value: 'label', label: 'Metric labels' },
    { value: 'value', label: 'Metric values' },
  ],
  overall: [
    { value: 'label', label: 'Label' },
    { value: 'value', label: 'Value' },
  ],
  recommendation: [
    { value: 'heading', label: 'Heading' },
    { value: 'body', label: 'Program' },
    { value: 'note', label: 'Counselor note' },
  ],
  signature: [
    { value: 'name', label: 'Name' },
    { value: 'role', label: 'Role' },
  ],
  note: [{ value: 'body', label: 'Note' }],
  footer: [{ value: 'body', label: 'Footer text' }],
  text: [{ value: 'body', label: 'Text' }],
};

export function textPartOptions(type = 'text') {
  return TEXT_PARTS_BY_TYPE[type] ?? [];
}

export function typographyDefaults(type = 'text') {
  const defaults = {
    font_family: 'Arial, Helvetica, sans-serif',
    font_size_pt: 10,
    font_weight: 400,
    text_color: '#0f172a',
    text_align: 'left',
    line_height: 1.2,
    letter_spacing_px: 0,
  };

  return {
    ...defaults,
    ...( {
      header: { font_size_pt: 20, font_weight: 700, text_align: 'center' },
      exam_details: { text_align: 'center' },
      overall: { font_size_pt: 12, font_weight: 700 },
      signature: { text_align: 'center' },
      footer: { font_size_pt: 8.5, text_color: '#64748b', text_align: 'center' },
      field: { font_size_pt: 10.5 },
      score_table: { font_size_pt: 9.5 },
      recommendation: { font_size_pt: 10.5 },
      note: { font_size_pt: 9.5 },
    }[type] ?? {}),
  };
}

export function textPartDefaults(type = 'text', part = 'body') {
  const base = typographyDefaults(type);
  const parts = {
    header: {
      title: { ...base },
      subtitle: { ...base, font_size_pt: 12, font_weight: 500 },
    },
    identity: {
      label: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: '#334155' },
      value: { ...base, font_size_pt: 9.5 },
    },
    field: {
      label: { ...base, font_size_pt: 9, font_weight: 700 },
      value: { ...base, font_size_pt: 10 },
    },
    score_table: {
      title: { ...base, font_size_pt: 12, font_weight: 700 },
      heading: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: '#334155' },
      body: { ...base, font_size_pt: 9 },
    },
    data_table: {
      title: { ...base, font_size_pt: 12, font_weight: 700 },
      heading: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: '#334155' },
      body: { ...base, font_size_pt: 9 },
    },
    kpi_grid: {
      label: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: '#64748b' },
      value: { ...base, font_size_pt: 14, font_weight: 800 },
    },
    overall: {
      label: { ...base, font_size_pt: 11, font_weight: 700 },
      value: { ...base, font_size_pt: 12, font_weight: 700 },
    },
    recommendation: {
      heading: { ...base, font_size_pt: 10, font_weight: 700 },
      body: { ...base, font_size_pt: 11, font_weight: 600 },
      note: { ...base, font_size_pt: 9.5, text_color: '#334155' },
    },
    signature: {
      name: { ...base, font_size_pt: 10, font_weight: 700, text_align: 'center' },
      role: { ...base, font_size_pt: 8.5, text_color: '#64748b', text_align: 'center' },
    },
    note: { body: { ...base, font_size_pt: 9.5 } },
    footer: { body: { ...base, font_size_pt: 8.5, text_color: '#64748b', text_align: 'center' } },
    text: { body: { ...base } },
  };
  return parts[type]?.[part] ?? base;
}

export function appearanceDefaults(type = 'text') {
  return {
    background_color: ['identity', 'score_table', 'overall'].includes(type)
      ? '#f8fafc'
      : type === 'recommendation' ? '#ecfdf5' : 'transparent',
    border_color: type === 'recommendation' ? '#a7f3d0' : '#cbd5e1',
    border_width_px: ['identity', 'score_table', 'overall', 'recommendation', 'data_table', 'kpi_grid'].includes(type) ? 1 : 0,
    border_style: 'solid',
    border_radius_mm: type === 'recommendation' ? 1 : 0,
  };
}

export function spacingDefaults(type = 'text') {
  return {
    padding_mm: type === 'header' ? 2.5 : ['identity', 'score_table', 'recommendation'].includes(type) ? 3 : ['divider', 'spacer'].includes(type) ? 0 : 2,
  };
}

function safeColor(value, fallback = '#0f172a') {
  const color = String(value ?? '').trim();
  return color === 'transparent' || /^#[0-9a-f]{3,8}$/i.test(color) ? color : fallback;
}

function normalizeTypography(raw = {}, fallback = typographyDefaults('text')) {
  const typography = { ...fallback, ...raw };
  const fontFamilies = new Set(RESULT_SHEET_FONT_OPTIONS.map((font) => font.value));
  typography.font_family = fontFamilies.has(typography.font_family) ? typography.font_family : fallback.font_family;
  typography.font_size_pt = clamp(Number(typography.font_size_pt), 6, 48, fallback.font_size_pt);
  typography.font_weight = [400, 500, 600, 700, 800].includes(Number(typography.font_weight)) ? Number(typography.font_weight) : fallback.font_weight;
  typography.text_color = safeColor(typography.text_color, fallback.text_color);
  typography.text_align = RESULT_SHEET_TEXT_ALIGN_OPTIONS.some((option) => option.value === typography.text_align) ? typography.text_align : fallback.text_align;
  typography.line_height = clamp(Number(typography.line_height), 0.8, 2.4, fallback.line_height);
  typography.letter_spacing_px = clamp(Number(typography.letter_spacing_px), -2, 8, fallback.letter_spacing_px);
  return typography;
}

export function normalizeComponentProps(type, props = {}) {
  const typography = normalizeTypography({
    ...typographyDefaults(type),
    ...(props.typography ?? {}),
  }, typographyDefaults(type));
  const appearance = {
    ...appearanceDefaults(type),
    ...(props.appearance ?? {}),
  };
  const spacing = {
    ...spacingDefaults(type),
    ...(props.spacing ?? {}),
  };

  if (props.font_size !== undefined && props.typography?.font_size_pt === undefined) typography.font_size_pt = props.font_size;
  if (props.bold !== undefined && props.typography?.font_weight === undefined) typography.font_weight = props.bold ? 700 : 400;
  if (props.background !== undefined && props.appearance?.background_color === undefined) appearance.background_color = props.background;
  if (props.border && props.appearance?.border_width_px === undefined) appearance.border_width_px = 1;

  appearance.background_color = safeColor(appearance.background_color, 'transparent');
  appearance.border_color = safeColor(appearance.border_color, '#cbd5e1');
  appearance.border_width_px = clamp(Number(appearance.border_width_px), 0, 4, 0);
  appearance.border_style = RESULT_SHEET_BORDER_STYLE_OPTIONS.some((option) => option.value === appearance.border_style) ? appearance.border_style : 'solid';
  appearance.border_radius_mm = clamp(Number(appearance.border_radius_mm), 0, 8, 0);
  spacing.padding_mm = clamp(Number(spacing.padding_mm), 0, 12, 2);

  const textStyles = Object.fromEntries(textPartOptions(type).map((part) => [
    part.value,
    normalizeTypography({ ...textPartDefaults(type, part.value), ...(props.text_styles?.[part.value] ?? {}) }, textPartDefaults(type, part.value)),
  ]));

  return { ...props, typography, text_styles: textStyles, appearance, spacing };
}

export function componentStyle(component, props = component.props ?? {}, pageMarginMm = 0) {
  const normalized = normalizeComponentProps(component.type, props);
  const typography = normalized.typography;
  const appearance = normalized.appearance;
  const spacing = normalized.spacing;
  const border = appearance.border_width_px > 0
    ? `${appearance.border_width_px}px ${appearance.border_style} ${appearance.border_color}`
    : '0 solid transparent';

  return [
    'position:absolute',
    'box-sizing:border-box',
    'min-width:0',
    'overflow-wrap:anywhere',
    `left:calc(${pageMarginMm}mm + ${component.x}mm)`,
    `top:calc(${pageMarginMm}mm + ${component.y}mm)`,
    `width:${component.w}mm`,
    `min-height:${component.h}mm`,
    `padding:${spacing.padding_mm}mm`,
    `font-family:${typography.font_family}`,
    `font-size:${typography.font_size_pt}pt`,
    `font-weight:${typography.font_weight}`,
    `color:${typography.text_color}`,
    `text-align:${typography.text_align}`,
    `line-height:${typography.line_height}`,
    `letter-spacing:${typography.letter_spacing_px}px`,
    `background-color:${appearance.background_color}`,
    `border:${border}`,
    `border-radius:${appearance.border_radius_mm}mm`,
  ].join(';') + ';';
}

export function inlineTypographyStyle(typography = {}) {
  const normalized = normalizeTypography(typography, typographyDefaults('text'));
  return `font-family:${normalized.font_family};font-size:${normalized.font_size_pt}pt;font-weight:${normalized.font_weight};color:${normalized.text_color};text-align:${normalized.text_align};line-height:${normalized.line_height};letter-spacing:${normalized.letter_spacing_px}px;`;
}

export function typographyStyle(props = {}) {
  return inlineTypographyStyle(normalizeComponentProps('text', props).typography);
}

export function sampleValue(binding, fallback = 'Sample value') {
  return {
    institution_name: 'NEXIAM',
    institution_exam_name: 'ICAT Examination',
    institution_exam_acronym: 'ICAT',
    applicant_reference: '2026-00042',
    family_name: 'DOE',
    first_name: 'JUAN',
    middle_name: 'DELA CRUZ',
    suffix: '',
    sex: 'M',
    gwa: '92.40',
    course_applied_code: 'BSCS',
    strand: 'STEM',
    exam_date: '22 Aug 2026',
    exam_time: '08:00',
    room_name: 'Room 1',
    overall_pct: '88.40%',
    recommended_course: 'BS Computer Science',
    counselor_name: 'Guidance Counselor',
    counselor_comments: 'Applicant meets the recommended program criteria.',
    generated_at: 'October 13, 2026',
    page_i: '1',
    page_all: '1',
    pg_i: '1',
    pg_all: '1',
    academic_cycle_name: '1st Semester · AY 2026–2027',
    prepared_by_name: 'Authorized Staff',
    document_title: 'Official Office Document',
    custom_heading: 'Official Office Document',
    custom_note: 'Official guidance note.',
  }[binding] ?? fallback;
}

export function replaceMacros(value, fallback = 'Sample value') {
  return String(value ?? '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, binding) => sampleValue(binding, fallback));
}

function clamp(value, min, max, fallback) {
  return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : fallback;
}


export const COMPONENT_TEMPLATE_DEFAULTS = {
  header: '<section class="result-sheet-header"><h1>{{slot:title}}</h1><p>{{slot:subtitle}}</p></section>',
  identity: '<table class="result-sheet-identity"><tbody><tr><th>Applicant No.</th><td>{{slot:value}}</td></tr><tr><th>Name</th><td>{{slot:text}}</td></tr></tbody></table>',
  field: '<div class="result-sheet-field"><strong>{{slot:label}}</strong> {{slot:value}}</div>',
  text: '<p class="result-sheet-text">{{slot:text}}</p>',
  note: '<p class="result-sheet-note"><em>{{slot:text}}</em></p>',
  footer: '<p class="result-sheet-footer">{{slot:text}}</p>',
  signature: '<div class="result-sheet-signature"><strong>{{slot:value}}</strong><br><span>{{slot:label}}</span></div>',
  recommendation: '<section class="result-sheet-recommendation"><strong>{{slot:title}}</strong><p>{{slot:value}}</p><p>{{slot:text}}</p></section>',
}

const COMPONENT_TEMPLATE_ALLOWED_TAGS = new Set(['section', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'strong', 'em', 'small', 'br', 'table', 'thead', 'tbody', 'tr', 'th', 'td']);

export function componentTemplateDefaults(type) {
  return COMPONENT_TEMPLATE_DEFAULTS[type] ?? '<div>{{slot:text}}</div>';
}

export function validateComponentTemplate(html) {
  const source = String(html ?? '');
  const errors = [];
  if (!source.trim()) errors.push('Template HTML cannot be empty.');
  if (source.length > 12000) errors.push('Template HTML must be 12,000 characters or fewer.');
  if (/<\s*(script|iframe|object|embed|style|link)\b/i.test(source)) errors.push('Executable or external document tags are not allowed.');
  if (/\son[a-z]+\s*=|javascript\s*:/i.test(source)) errors.push('Event handlers and javascript URLs are not allowed.');
  for (const match of source.matchAll(/<\/?\s*([a-z0-9-]+)/gi)) {
    if (!COMPONENT_TEMPLATE_ALLOWED_TAGS.has(match[1].toLowerCase())) errors.push(`Tag <${match[1]}> is not allowed.`);
  }
  return [...new Set(errors)];
}

export function sanitizeComponentTemplate(html) {
  let source = String(html ?? '')
    .replace(/<\s*(script|iframe|object|embed|style|link)\b[^>]*>[\s\S]*?<\/\s*\1\s*>/gi, '')
    .replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    .replace(/javascript\s*:/gi, '');
  source = source.replace(/<\/?\s*([a-z0-9-]+)([^>]*)>/gi, (full, tag, attrs) => {
    if (!COMPONENT_TEMPLATE_ALLOWED_TAGS.has(String(tag).toLowerCase())) return '';
    return `<${full.startsWith('</') ? '/' : ''}${tag}${attrs}>`;
  });
  return source;
}

export function renderComponentTemplate(template, component, props = {}, interpolate = replaceMacros) {
  const normalized = normalizeComponentProps(component.type, props);
  const slots = {
    title: interpolate(normalized.title ?? normalized.text ?? '{{institution_name}}'),
    subtitle: interpolate(normalized.subtitle ?? '{{institution_exam_name}}'),
    label: interpolate(normalized.label ?? component.type),
    value: interpolate(normalized.binding ? `{{${normalized.binding}}}` : 'Sample value'),
    text: interpolate(normalized.text ?? normalized.title ?? 'Sample value'),
  };
  let html = sanitizeComponentTemplate(template);
  html = html.replace(/\{\{slot:([a-z_]+)\}\}/gi, (_, name) => escapeHtml(slots[name] ?? 'Sample value'));
  return html;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
}
