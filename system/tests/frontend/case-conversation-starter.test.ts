// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CaseConversationStarter from '../../resources/js/Components/Admission/CaseConversationStarter.svelte';

const routerMocks = vi.hoisted(() => ({
    get: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/svelte', () => ({ router: routerMocks }));

const programs = [
    { code: 'BSIT', name: 'Information Technology' },
    { code: 'BSCS', name: 'Computer Science' },
];
const candidates = [
    { id: '21', name: 'Alex Applicant', reference_code: 'APP-2026-00021', cycle_code: '2026-2027' },
    { id: '22', name: 'Sam Student', reference_code: 'APP-2026-00022', cycle_code: '2026-2027' },
];

function renderStarter() {
    return render(CaseConversationStarter, {
        props: {
            programs,
            cycleCode: '2026-2027',
            principalContext: 'staff-4:session-2:case_threads',
            applicantLookupUrl: '/staff/inquiries/case-applicants',
            destination: 'messages',
            destinationUrl: '/staff/inquiries?group=case_threads',
        },
    });
}

function applicantsResponse(programCode: string, data = candidates) {
    return new Response(JSON.stringify({
        data,
        has_more: false,
        program_code: programCode,
        cycle_code: '2026-2027',
    }), { status: 200 });
}

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
    vi.unstubAllGlobals();
});

describe('Messages conversation starter', () => {
    it('clears unsent text when the applicant or audience changes', async () => {
        const fetchMock = vi.fn().mockResolvedValue(applicantsResponse('BSIT'));
        vi.stubGlobal('fetch', fetchMock);
        renderStarter();

        await fireEvent.click(screen.getByRole('button', { name: /Start conversation/ }));
        await fireEvent.change(screen.getByRole('combobox', { name: 'Degree program' }), { target: { value: 'BSIT' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Search applicants' }));

        const applicantPicker = await screen.findByRole('combobox', { name: 'Eligible applicants' });
        await fireEvent.change(applicantPicker, { target: { value: '21' } });
        const subject = screen.getByRole('textbox', { name: 'Subject / case reason' }) as HTMLInputElement;
        const message = screen.getByRole('textbox', { name: 'First message' }) as HTMLTextAreaElement;
        await fireEvent.input(subject, { target: { value: 'Review course alignment' } });
        await fireEvent.input(message, { target: { value: 'Please review the applicant’s course background.' } });

        await fireEvent.change(applicantPicker, { target: { value: '22' } });
        expect(subject.value).toBe('');
        expect(message.value).toBe('');
        expect(screen.getByRole('status').textContent).toContain('Applicant changed');

        await fireEvent.input(subject, { target: { value: 'Review course alignment' } });
        await fireEvent.input(message, { target: { value: 'A new message for the selected applicant.' } });
        await fireEvent.change(screen.getByRole('combobox', { name: 'Conversation audience' }), { target: { value: 'applicant_support' } });

        expect(subject.value).toBe('');
        expect(message.value).toBe('');
        expect(screen.getByRole('status').textContent).toContain('Audience changed');
        await fireEvent.input(subject, { target: { value: 'Another subject' } });
        await fireEvent.input(message, { target: { value: 'A message before changing programs.' } });
        await fireEvent.change(screen.getByRole('combobox', { name: 'Degree program' }), { target: { value: 'BSCS' } });
        expect(subject.value).toBe('');
        expect(message.value).toBe('');
        expect(screen.queryByRole('combobox', { name: 'Eligible applicants' })).toBeNull();
        expect(screen.getByRole('status').textContent).toContain('Program changed');
    });

    it('ignores an old-program response that arrives after the next program results', async () => {
        const oldRequest = Promise.withResolvers<Response>();
        const nextCandidates = [
            { id: '31', name: 'Casey Current', reference_code: 'APP-2026-00031', cycle_code: '2026-2027' },
        ];
        vi.stubGlobal('fetch', vi.fn()
            .mockImplementationOnce(() => oldRequest.promise)
            .mockResolvedValueOnce(applicantsResponse('BSCS', nextCandidates)));
        renderStarter();

        await fireEvent.click(screen.getByRole('button', { name: /Start conversation/ }));
        const programPicker = screen.getByRole('combobox', { name: 'Degree program' });
        await fireEvent.change(programPicker, { target: { value: 'BSIT' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Search applicants' }));
        await fireEvent.change(programPicker, { target: { value: 'BSCS' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Search applicants' }));
        await screen.findByRole('option', { name: 'Casey Current · APP-2026-00031' });

        oldRequest.resolve(applicantsResponse('BSIT'));
        await waitFor(() => {
            expect(screen.queryByRole('option', { name: 'Alex Applicant · APP-2026-00021' })).toBeNull();
            expect(screen.getByRole('option', { name: 'Casey Current · APP-2026-00031' })).toBeTruthy();
            expect((programPicker as HTMLSelectElement).value).toBe('BSCS');
        });
    });

    it('retains the selected audience and draft after ordinary validation failure', async () => {
        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce(applicantsResponse('BSIT'))
            .mockResolvedValueOnce(new Response(JSON.stringify({
                message: 'The message needs correction.',
                errors: { body: ['Review this message before sending.'] },
            }), { status: 422 })));
        renderStarter();

        await fireEvent.click(screen.getByRole('button', { name: /Start conversation/ }));
        await fireEvent.change(screen.getByRole('combobox', { name: 'Degree program' }), { target: { value: 'BSIT' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Search applicants' }));
        await fireEvent.change(await screen.findByRole('combobox', { name: 'Eligible applicants' }), { target: { value: '21' } });
        const audience = screen.getByRole('combobox', { name: 'Conversation audience' }) as HTMLSelectElement;
        await fireEvent.change(audience, { target: { value: 'applicant_support' } });
        const subject = screen.getByRole('textbox', { name: 'Subject / case reason' }) as HTMLInputElement;
        const message = screen.getByRole('textbox', { name: 'First message' }) as HTMLTextAreaElement;
        await fireEvent.input(subject, { target: { value: 'Keep this subject' } });
        await fireEvent.input(message, { target: { value: 'Keep this applicant-visible draft.' } });
        await fireEvent.click(screen.getAllByRole('button', { name: 'Start conversation' }).at(-1)!);

        await screen.findByRole('alert');
        expect(audience.value).toBe('applicant_support');
        expect(subject.value).toBe('Keep this subject');
        expect(message.value).toBe('Keep this applicant-visible draft.');
    });
});
