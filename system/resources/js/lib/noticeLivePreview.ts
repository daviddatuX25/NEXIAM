export const DEFAULT_SAMPLE_CONTEXT: Record<string, string> = {
    applicant_name: 'Maria Santos',
    application_no: 'APP-2026-0814',
    session_code: 'EXAM-2026-001',
    exam_date: 'October 25, 2026',
    exam_time: '8:00 AM',
    room_name: 'Room 302',
    building: 'Testing Center Building A',
    seat_number: 'Seat 14',
    institution_name: 'State University',
    portal_url: '/applicant/portal',
    preferred_program: 'BS Information Technology',
};

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (character) => {
        switch (character) {
            case '&':
                return '&amp;';
            case '<':
                return '&lt;';
            case '>':
                return '&gt;';
            case '"':
                return '&quot;';
            case "'":
                return '&#39;';
            default:
                return character;
        }
    });
}

function stringValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

export function interpolateTemplateText(
    text: string,
    sampleContext?: Record<string, unknown>,
): string {
    const source = typeof text === 'string' ? text : '';
    const context = sampleContext ?? DEFAULT_SAMPLE_CONTEXT;

    return source.replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (match, rawToken: string) => {
        const token = rawToken.trim();

        if (token && Object.prototype.hasOwnProperty.call(context, token)) {
            return escapeHtml(stringValue(context[token]));
        }

        return token ? `{{${token}}}` : match;
    });
}


function isSafePreviewUrl(value: string): boolean {
    return value.startsWith('/') && !value.startsWith('//')
        || /^data:image\/(?:png|jpeg|gif|webp);base64,/i.test(value)
        || value.startsWith('blob:');
}

function renderAssets(assets: Array<{ asset_id: string; preview_url: string; alt?: string }> | undefined): string {
    if (!assets?.length) {
        return '';
    }

    return assets.map((asset) => {
        const previewUrl = typeof asset.preview_url === 'string' && isSafePreviewUrl(asset.preview_url)
            ? escapeHtml(asset.preview_url)
            : '';
        const alt = escapeHtml(typeof asset.alt === 'string' ? asset.alt : '');

        return `<div style="margin: 1rem 0;"><img src="${previewUrl}" alt="${alt}" style="display: block; max-width: 100%; height: auto; border-radius: 0.5rem;" /></div>`;
    }).join('');
}

export function renderLiveEmailHtml(params: {
    subject: string;
    bodyText: string;
    actionLabel?: string | null;
    actionEnabled?: boolean;
    sampleContext?: Record<string, unknown>;
    assets?: Array<{ asset_id: string; preview_url: string; alt?: string }>;
}): string {
    const subject = interpolateTemplateText(escapeHtml(params.subject ?? ''), params.sampleContext);
    const bodyText = interpolateTemplateText(escapeHtml(params.bodyText ?? ''), params.sampleContext);
    const paragraphs = bodyText.split(/\n\n+/).map((paragraph) => {
        const content = paragraph.replace(/\n/g, '<br/>');

        return `<p class="paragraph" style="margin: 0 0 1rem 0; line-height: 1.6; color: #1f2937;">${content}</p>`;
    }).join('');
    const actionLabel = params.actionLabel
        ? interpolateTemplateText(escapeHtml(params.actionLabel), params.sampleContext)
        : '';
    const action = params.actionEnabled && actionLabel.trim()
        ? `<div style="margin: 1.5rem 0;"><a style="background-color: #059669; color: #ffffff; padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; display: inline-block;">${actionLabel}</a></div>`
        : '';
    const assets = renderAssets(params.assets);

    return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>${subject}</title>
</head>
<body style="margin: 0; padding: 2rem 1rem; background-color: #f3f4f6; font-family: Arial, Helvetica, sans-serif;">
<div style="max-width: 640px; margin: 0 auto; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 0.75rem; background-color: #ffffff; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);">
<div style="padding: 2rem 2rem 1rem;">
<h1 style="margin: 0 0 1.5rem; color: #111827; font-size: 1.5rem; line-height: 1.3;">${subject}</h1>
${paragraphs}${assets}${action}
</div>
<div style="padding: 1rem 2rem; border-top: 1px solid #e5e7eb; background-color: #f9fafb; color: #6b7280; font-size: 0.8125rem; line-height: 1.5;">
<p style="margin: 0;">This message was sent by your university. Please do not reply to this automated notice.</p>
</div>
</div>
</body>
</html>`;
}

export function renderLivePortalHtml(params: {
    subject: string;
    bodyText: string;
    actionLabel?: string | null;
    actionEnabled?: boolean;
    sampleContext?: Record<string, unknown>;
}): { title: string; body: string; action?: { label: string } | null } {
    const title = interpolateTemplateText(params.subject ?? '', params.sampleContext);
    const body = interpolateTemplateText(params.bodyText ?? '', params.sampleContext);
    const label = params.actionLabel
        ? interpolateTemplateText(params.actionLabel, params.sampleContext)
        : '';

    return {
        title,
        body,
        action: params.actionEnabled && label ? { label } : null,
    };
}
