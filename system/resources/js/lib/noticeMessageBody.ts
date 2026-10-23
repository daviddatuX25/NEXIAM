import type { MessageState } from './noticeMessageComposer';

type BlueprintBlock = Record<string, unknown>;
type BlueprintInput = { blocks?: BlueprintBlock[] } | null | undefined;
type MessageBlueprint = NonNullable<MessageState['email_blueprint']>;
type TextRun = { text: string } | { token: string };
function asRuns(value: unknown): TextRun[] {
    let runs: unknown[];
    if (Array.isArray(value)) {
        runs = value;
    } else if (typeof value === 'string') {
        runs = [{ text: value }];
    } else if (value && typeof value === 'object' && !Array.isArray(value) && 'runs' in value && Array.isArray(value.runs)) {
        runs = value.runs;
    } else if (value && typeof value === 'object' && !Array.isArray(value) && 'text' in value && typeof value.text === 'string') {
        runs = [value];
    } else {
        runs = [];
    }

    return runs.flatMap((run): TextRun[] => {
        if (typeof run === 'string') {
            return [{ text: run }];
        }
        if (!run || typeof run !== 'object' || Array.isArray(run)) {
            return [];
        }
        if ('token' in run && typeof run.token === 'string') {
            return [{ token: run.token }];
        }
        if ('text' in run && typeof run.text === 'string') {
            return [{ text: run.text }];
        }
        return [];
    });
}

function blockText(block: BlueprintBlock): string {
    return asRuns(block.runs ?? block.text).map((run) => 'token' in run ? `{{${run.token}}}` : run.text).join('');
}

/** Convert semantic paragraph and heading blocks to plain authoring text. */
export function blueprintToText(blueprint: BlueprintInput): string {
    return (blueprint?.blocks ?? [])
        .filter((block) => block.type === 'paragraph' || block.type === 'heading')
        .map(blockText)
        .join('\n\n');
}

/** Find token names in the order they appear, retaining duplicates. */
export function extractTokens(text: string): string[] {
    return Array.from(text.matchAll(/\{\{\s*([^{}]+?)\s*\}\}/g), (match) => match[1].trim());
}

function parseTextRuns(paragraph: string): TextRun[] {
    const runs: TextRun[] = [];
    const pattern = /\{\{\s*([^{}]+?)\s*\}\}/g;
    let cursor = 0;

    for (const match of paragraph.matchAll(pattern)) {
        const index = match.index ?? 0;
        if (index > cursor) {
            runs.push({ text: paragraph.slice(cursor, index) });
        }
        runs.push({ token: match[1].trim() });
        cursor = index + match[0].length;
    }

    if (cursor < paragraph.length) {
        runs.push({ text: paragraph.slice(cursor) });
    }

    return runs.length > 0 ? runs : [{ text: paragraph }];
}

function textParagraphs(text: string): BlueprintBlock[] {
    if (text.length === 0) {
        return [];
    }

    return text
        .split('\n\n')
        .map((paragraph) => ({ type: 'paragraph', runs: parseTextRuns(paragraph) }));
}

/**
 * Convert plain authoring text to flow blocks, retaining existing non-text
 * blocks and their relative order. New paragraphs replace the existing text
 * block run at its first position, or follow retained blocks when none exist.
 */
export function textToBlueprint(text: string, existingBlueprint?: BlueprintInput): MessageBlueprint {
    const paragraphs = textParagraphs(text);
    const existingBlocks = existingBlueprint?.blocks ?? [];
    const textBlockIndexes = existingBlocks.flatMap((block, index) =>
        block.type === 'paragraph' || block.type === 'heading' ? [index] : [],
    );
    const blocks: BlueprintBlock[] = [];
    let paragraphIndex = 0;

    if (textBlockIndexes.length === 0) {
        blocks.push(...existingBlocks, ...paragraphs);
    } else {
        const lastTextBlockIndex = textBlockIndexes[textBlockIndexes.length - 1];

        existingBlocks.forEach((block, index) => {
            if (block.type !== 'paragraph' && block.type !== 'heading') {
                blocks.push(block);
                return;
            }

            const paragraph = paragraphs[paragraphIndex];
            if (paragraph) {
                blocks.push({ ...paragraph, type: block.type });
                paragraphIndex++;
            }

            if (index === lastTextBlockIndex && paragraphIndex < paragraphs.length) {
                blocks.push(...paragraphs.slice(paragraphIndex));
                paragraphIndex = paragraphs.length;
            }
        });
    }

    return { schema_version: 'notice-email-1', layout: 'flow', blocks };
}

/** Insert a token at a text cursor, adding only the whitespace needed at its edges. */
export function insertToken(text: string, token: string, cursorPosition = text.length): { newText: string; newCursor: number } {
    const position = Math.max(0, Math.min(text.length, cursorPosition));
    const tokenName = token.replace(/[{}]/g, '').trim();
    const placeholder = `{{${tokenName}}}`;
    const needsLeadingSpace = position > 0 && !/\s/.test(text[position - 1]);
    const needsTrailingSpace = position < text.length && !/\s/.test(text[position]);
    const insertion = `${needsLeadingSpace ? ' ' : ''}${placeholder}${needsTrailingSpace ? ' ' : ''}`;
    const newText = `${text.slice(0, position)}${insertion}${text.slice(position)}`;

    return { newText, newCursor: position + insertion.length };
}

/** Ensure message editing always starts with the canonical semantic blueprint. */
export function ensureBlueprint(state: MessageState): MessageState {
    return {
        ...state,
        email_blueprint: state.email_blueprint ?? { schema_version: 'notice-email-1', layout: 'flow', blocks: [] },
    };
}
