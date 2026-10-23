import { describe, expect, it } from 'vitest';
import { getTour } from '../../resources/js/lib/walkthrough/tours';

const base = {
    entityKey: 'attempt-1', safetyKey: 'appointment', freshness: 'current' as const,
    pageState: 'assigned_published', message: '', needsCorrection: false,
    canOpenCorrection: false, slotCount: 0, availableSlotCount: 0,
    reservationError: null,
    appointment: { date: '2026-10-06', startTime: '09:00', endTime: '11:00', room: 'Room A', published: true, source: 'staff_assigned' },
};

function schedule(context = {}) {
    return getTour('applicant-exam-schedule', { ...base, ...context })!;
}

function text(context = {}) {
    return schedule(context).steps.map((step) => `${step.title} ${step.text}`).join(' ');
}

describe('Applicant walkthrough factual boundaries', () => {
    it.each([false, undefined])('omits physical-seat instructions when seating is %s', (enabled) => {
        expect(text({ seatingEnabled: enabled })).not.toMatch(/\bseat\b|seating|seat code|seat number/i);
    });

    it('does not invent seat codes in the appointment card when seating is enabled', () => {
        const tour = schedule({ seatingEnabled: true });
        expect(tour.steps.find((step) => step.id === 'appointment-workflow')!.text).not.toMatch(/seat code|seat number|call time|campus building/i);
        expect(tour.steps.find((step) => step.id === 'guidance')!.text).toMatch(/seat/i);
    });

    it.each(['pending_acceptance', 'waiting_for_reservation_open', 'denied'])('does not offer booking in %s', (pageState) => {
        const tour = schedule({ pageState, appointment: null });
        expect(tour.steps.find((step) => step.id === 'scheduling-concept')!.text).not.toMatch(/pick an open date|choose an available|confirm your reservation/i);
        expect(tour.steps.find((step) => step.id === 'appointment-workflow')!.text).not.toMatch(/card.*shows|check your appointment card/i);
    });

    it('offers reservation only for an open calendar', () => {
        const tour = schedule({ pageState: 'calendar_open', appointment: null, availableSlotCount: 2 });
        expect(tour.steps.find((step) => step.id === 'scheduling-concept')!.text).toMatch(/choose an available/i);
        expect(tour.steps.find((step) => step.id === 'scheduling-concept')!.text).not.toMatch(/guaranteed|high school track/i);
    });

    it('does not treat an unpublished assignment as confirmed', () => {
        const tour = schedule({ pageState: 'assigned_tentative', appointment: { ...base.appointment, published: false } });
        expect(tour.steps.find((step) => step.id === 'appointment-workflow')!.text).toMatch(/not yet published/i);
    });

    it('does not fabricate exam-day requirements', () => {
        expect(text()).not.toMatch(/Mongol|30 minutes|school ID|Exam QR Pass/i);
    });

    it('does not promise self-booking after application review', () => {
        const tour = getTour('applicant-my-application')!;
        expect(tour.steps.find((step) => step.id === 'guidance')!.text).not.toMatch(/pick your test slot|official Exam QR Pass/i);
    });

    it('points document-check questions to the My Application checklist', () => {
        const tour = getTour('applicant-my-application')!;
        const documents = tour.steps.find((step) => step.id === 'review-concept')!;

        expect(documents.target).toEqual({ dataTour: 'my-application-documents' });
        expect(documents.text).toMatch(/document checklist/i);
    });

    it('describes the QR as a locator, not a seat or access guarantee', () => {
        const tour = getTour('applicant-qr', { entityKey: 'attempt-1', safetyKey: 'qr', freshness: 'current', pageState: 'ready', hasQr: true, attemptId: 1 })!;
        const content = tour.steps.map((step) => step.text).join(' ');
        expect(content).not.toMatch(/verify your seat|you are in|private exam scores|half a second/i);
        expect(content).toMatch(/identifies your admission attempt/i);
    });
    it('refers to university staff and avoids school staff in QR tour', () => {
        const tour = getTour('applicant-qr', { entityKey: 'attempt-1', safetyKey: 'qr', freshness: 'current', pageState: 'ready', hasQr: true, attemptId: 1 })!;
        const content = tour.steps.map((step) => step.text).join(' ');
        expect(content).toMatch(/university staff/i);
        expect(content).not.toMatch(/school staff/i);
    });
});

