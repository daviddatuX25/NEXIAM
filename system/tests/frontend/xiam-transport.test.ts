import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    fetchXiamMode,
    fetchXiamSnapshot,
    parseXiamStreamChunk,
    setXiamMode,
    streamXiamConversation,
    submitXiamTurn,
} from '../../resources/js/lib/xiam-transport';
import { isXiamSessionView } from '../../resources/js/types/xiam';

afterEach(() => {
    vi.unstubAllGlobals();
});
describe('xiam session still validates after run governance', () => {
    it('accepts session with populated conversation cursor', () => {
        expect(
            isXiamSessionView({
                contract_version: '2',
                surface: { key: 'staff', version: 1 },
                context_epoch: 'epoch',
                page_pack: null,
                publication_version: null,
                availability: 'degraded',
                active_mode: 'inquiry',
                interaction_modes: [
                    { key: 'inquiry', label: 'Inquiry' },
                    { key: 'assistant', label: 'Assistant' },
                ],
                disclosure: { key: 'd', version: 1, text: 't' },
                conversation: { id: 'c1', run_id: 'r1', cursor: 3 },
                usage: {
                    state: 'available',
                    used: 1,
                    limit: 50,
                    remaining: 49,
                    credit_balance: 0,
                    unit: 'asks',
                    period: 'day',
                    resets_at: null,
                },
                proposals: [],
                manual_fallbacks: [],
            }),
        ).toBe(true);
    });
});

describe('xiam preference and usage transport', () => {
    const usage = {
        state: 'available',
        used: 2,
        limit: 15,
        remaining: 13,
        credit_balance: 1,
        unit: 'asks',
        period: 'day',
        resets_at: '2026-10-06T00:00:00+08:00',
    };
    const modeView = {
        active_mode: 'assistant',
        interaction_modes: [
            { key: 'inquiry', label: 'Inquiry' },
            { key: 'assistant', label: 'Assistant' },
        ],
        context_epoch: 'epoch-2',
        usage,
    };

    it('loads the acknowledged server mode and complete usage view without caching', async () => {
        const fetchMock = vi.fn().mockResolvedValueOnce(
            new Response(JSON.stringify(modeView), { status: 200 }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(fetchXiamMode()).resolves.toEqual(modeView);
        expect(fetchMock).toHaveBeenCalledWith(
            '/xiam/mode',
            expect.objectContaining({ credentials: 'same-origin', cache: 'no-store' }),
        );

        const blockedView = {
            ...modeView,
            active_mode: 'inquiry',
            interaction_modes: [],
        };
        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify(blockedView), { status: 200 }),
        );
        await expect(fetchXiamMode()).resolves.toEqual(blockedView);
    });

    it('posts mode and context epoch and rejects a mismatched acknowledgement', async () => {
        const fetchMock = vi.fn().mockResolvedValueOnce(
            new Response(JSON.stringify(modeView), { status: 200 }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(setXiamMode('assistant', 'epoch-current')).resolves.toEqual(modeView);
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            mode: 'assistant',
            context_epoch: 'epoch-current',
        });

        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify({ ...modeView, active_mode: 'inquiry' }), { status: 200 }),
        );
        await expect(setXiamMode('assistant', 'epoch-current')).rejects.toThrow('mode_ack_invalid');
    });

    it('returns usage from accepted turns and attaches structured quota errors', async () => {
        const accepted = {
            conversation_id: 'conversation-1',
            turn_id: 'turn-1',
            run_id: 'run-1',
            context_epoch: 'epoch-1',
            cursor: 1,
            usage: { ...usage, used: 3, remaining: 12 },
        };
        const fetchMock = vi.fn().mockResolvedValueOnce(
            new Response(JSON.stringify(accepted), { status: 202 }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(submitXiamTurn({ message: 'Help me' })).resolves.toEqual(accepted);
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).not.toHaveProperty('mode');

        const exhausted = { ...usage, state: 'exhausted', used: 15, remaining: 0 };
        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify({ error: 'quota_exhausted', usage: exhausted }), {
                status: 429,
            }),
        );
        await expect(submitXiamTurn({ message: 'One more' })).rejects.toMatchObject({
            message: 'quota_exhausted',
            status: 429,
            code: 'quota_exhausted',
            usage: exhausted,
        });
    });

    it('preserves usage from a settled snapshot', async () => {
        const snapshot = {
            conversation_id: 'conversation-1',
            context_epoch: 'epoch-1',
            cursor: 4,
            usage: { ...usage, used: 4, remaining: 11 },
            run: { id: 'run-1', status: 'succeeded' },
            messages: [],
            events: [],
        };
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(JSON.stringify(snapshot), { status: 200 })),
        );
        await expect(fetchXiamSnapshot('conversation-1')).resolves.toMatchObject({
            usage: snapshot.usage,
        });
    });
});

describe('xiam stream protocol', () => {
    it('reassembles partial SSE frames and preserves custom UI parts', () => {
        const first = parseXiamStreamChunk(
            'data: {"type":"text-start","id":"xiam-run"}\n\ndata: {"type":"text-delta","id":"xiam-run","delta":"Room ',
        );

        expect(first.parts).toEqual([{ type: 'text-start', id: 'xiam-run' }]);

        const second = parseXiamStreamChunk(
            `${first.remainder}102 is ready."}\n\ndata: {"type":"data-xiam-citation","data":{"citations":[]}}\n\ndata: [DONE]\n\n`,
        );

        expect(second.parts).toEqual([
            {
                type: 'text-delta',
                id: 'xiam-run',
                delta: 'Room 102 is ready.',
            },
            {
                type: 'data-xiam-citation',
                data: { citations: [] },
            },
            { type: 'done' },
        ]);
        expect(second.remainder).toBe('');
    });

    it('preserves step labels and skills and avatar emotions through the stream', async () => {
        const stream = [
            'data: {"type":"data-xiam-steps","data":{"label":"Reviewing schedule","skill":"exam-scheduling"}}',
            '',
            'data: {"type":"data-xiam-avatar","data":{"emotion":"curious"}}',
            '',
            'data: [DONE]',
            '',
            '',
        ].join('\n');
        const parsed = parseXiamStreamChunk(stream);

        expect(parsed.parts).toEqual([
            {
                type: 'data-xiam-steps',
                data: { label: 'Reviewing schedule', skill: 'exam-scheduling' },
            },
            {
                type: 'data-xiam-avatar',
                data: { emotion: 'curious' },
            },
            { type: 'done' },
        ]);
        expect(parsed.remainder).toBe('');

        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(stream, { status: 200 })),
        );
        const streamedParts: unknown[] = [];

        await streamXiamConversation('conversation-1', 0, (part) => {
            streamedParts.push(part);
        });

        expect(streamedParts).toEqual(parsed.parts);
    });
});
