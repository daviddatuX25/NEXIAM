// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

import BatchVouchers from '../../resources/js/Pages/Staff/Personnel/BatchVouchers.svelte';

const targets = [
    {
        id: 11,
        name: 'Guidance Recipient',
        email: 'guidance@example.test',
        office: 'guidance',
        program_codes: ['BSIT'],
    },
    {
        id: 12,
        name: 'Registrar Recipient',
        email: null,
        office: 'registrar',
        program_codes: [],
    },
];

const issued = (
    userId: number,
    reference: string,
    token: string,
    fallback: string,
) => ({
    user_id: userId,
    status: 'issued',
    public_reference: reference,
    token,
    fallback,
    qr_url: `https://nexiam.example.test/staff/activate#v=${reference}&t=${token}`,
    print_hash: `hash-${userId}`,
    expires_at_display: 'Apr 1, 2027 9:00 AM UTC (UTC)',
});
function response(rows: unknown[]) {
    return { ok: true, json: async () => ({ rows }) };
}

let ids: string[];
let fetchRequest = vi.fn();
beforeEach(() => {
    ids = [
        '11111111-1111-4111-8111-111111111111',
        '22222222-2222-4222-8222-222222222222',
    ];
    vi.stubGlobal('crypto', {
        randomUUID: () => ids.shift() ?? '33333333-3333-4333-8333-333333333333',
    });
    fetchRequest = vi.fn();
    vi.stubGlobal('fetch', fetchRequest);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('issues only the selected permitted recipient and sends the current lifetime payload', async () => {
    fetchRequest.mockResolvedValueOnce(
        response([
            issued(
                12,
                'ABCDEFGH23',
                '0123456789abcdef0123456789abcdef',
                'ABCD-EFGH',
            ),
        ]),
    );
    render(BatchVouchers, { targets, canIssueNonExpiring: false });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue grouped vouchers' }),
    );
    await fireEvent.click(
        screen.getByRole('checkbox', { name: /Guidance Recipient/ }),
    );
    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue 1 voucher' }),
    );

    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(1));
    const [url, request] = fetchRequest.mock.calls[0];
    const body = JSON.parse(request.body);
    expect(url).toBe('/staff/personnel/vouchers/batch');
    expect(body).toMatchObject({
        operation_id: '11111111-1111-4111-8111-111111111111',
        targets: [{ id: 12 }],
        purpose: 'activation',
        mode: 'extended_180d',
        replace: false,
    });
    expect(await screen.findByText(/Fallback: ABCD-EFGH/)).toBeTruthy();
});

it('retries rows with the same operation and retains already revealed credentials for printing', async () => {
    const firstReveal = issued(
        11,
        'ABCDEFGH23',
        '0123456789abcdef0123456789abcdef',
        'ABCD-EFGH',
    );
    const secondReveal = issued(
        12,
        'BCDEFGHJ23',
        'abcdef0123456789abcdef0123456789',
        'BCDE-FGHJ',
    );
    fetchRequest
        .mockResolvedValueOnce(
            response([
                firstReveal,
                {
                    user_id: 12,
                    status: 'failed',
                    message: 'Target changed; retry safely.',
                },
            ]),
        )
        .mockResolvedValueOnce(
            response([
                {
                    user_id: 11,
                    status: 'already_issued',
                    public_reference: firstReveal.public_reference,
                },
                secondReveal,
            ]),
        )
        .mockResolvedValueOnce({ ok: false });
    render(BatchVouchers, { targets, canIssueNonExpiring: false });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue grouped vouchers' }),
    );
    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue 2 vouchers' }),
    );
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(1));
    await fireEvent.click(
        screen.getByRole('button', { name: 'Retry failed rows safely' }),
    );
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(2));

    const firstPayload = JSON.parse(fetchRequest.mock.calls[0][1].body);
    const retryPayload = JSON.parse(fetchRequest.mock.calls[1][1].body);
    expect(retryPayload.operation_id).toBe(firstPayload.operation_id);
    expect(await screen.findByText(/Fallback: ABCD-EFGH/)).toBeTruthy();
    expect(await screen.findByText(/Fallback: BCDE-FGHJ/)).toBeTruthy();

    await fireEvent.click(
        screen.getByRole('button', { name: 'Download grouped PDF' }),
    );
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(3));
    const printPayload = JSON.parse(fetchRequest.mock.calls[2][1].body);
    expect(fetchRequest.mock.calls[2][0]).toBe(
        '/staff/personnel/vouchers/batch/print',
    );
    expect(printPayload.cards).toHaveLength(2);
    expect(
        printPayload.cards.map((card: { token: string }) => card.token),
    ).toEqual([firstReveal.token, secondReveal.token]);
    expect(await screen.findByRole('alert')).toBeTruthy();
});

it('requires a separate confirmed replacement operation after a safe receipt', async () => {
    fetchRequest
        .mockResolvedValueOnce(
            response([
                {
                    user_id: 11,
                    status: 'replace_required',
                    message: 'A prior voucher exists.',
                },
            ]),
        )
        .mockResolvedValueOnce(
            response([
                issued(
                    11,
                    'ABCDEFGH23',
                    '0123456789abcdef0123456789abcdef',
                    'ABCD-EFGH',
                ),
            ]),
        );
    render(BatchVouchers, { targets, canIssueNonExpiring: false });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue grouped vouchers' }),
    );
    await fireEvent.click(
        screen.getByRole('checkbox', { name: /Registrar Recipient/ }),
    );
    await fireEvent.click(
        screen.getByRole('button', { name: 'Issue 1 voucher' }),
    );
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(1));
    await fireEvent.click(
        screen.getByRole('button', { name: 'Confirm replacement' }),
    );
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(2));

    const original = JSON.parse(fetchRequest.mock.calls[0][1].body);
    const replacement = JSON.parse(fetchRequest.mock.calls[1][1].body);
    expect(replacement.operation_id).not.toBe(original.operation_id);
    expect(replacement.replace).toBe(true);
});

it('allows proactive replacement and preserves replace state on retry', async () => {
    fetchRequest
        .mockResolvedValueOnce(
            response([
                { user_id: 11, status: 'failed', message: 'Temporary network failure.' },
            ]),
        )
        .mockResolvedValueOnce(
            response([
                issued(11, 'ABCDEFGH23', '0123456789abcdef0123456789abcdef', 'ABCD-EFGH'),
            ]),
        );
    render(BatchVouchers, { targets, canIssueNonExpiring: false });

    await fireEvent.click(screen.getByRole('button', { name: 'Issue grouped vouchers' }));
    await fireEvent.click(screen.getByRole('checkbox', { name: /Registrar Recipient/ }));
    await fireEvent.click(screen.getByRole('checkbox', { name: /Replace existing vouchers/ }));
    await fireEvent.click(screen.getByRole('button', { name: 'Issue 1 voucher' }));

    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(1));
    const firstBody = JSON.parse(fetchRequest.mock.calls[0][1].body);
    expect(firstBody.replace).toBe(true);

    await fireEvent.click(await screen.findByRole('button', { name: 'Retry failed rows safely' }));
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(2));
    const retryBody = JSON.parse(fetchRequest.mock.calls[1][1].body);
    expect(retryBody.operation_id).toBe(firstBody.operation_id);
    expect(retryBody.replace).toBe(true);
});
