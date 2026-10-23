import { describe, expect, it } from 'vitest';
import { classifyLoginQr } from '../../resources/js/lib/login-qr-classifier.js';

describe('general login QR classifier', () => {
    const origin = 'https://nexiam.example.test/login';

    it('accepts only an exact same-origin activation fragment without consuming it', () => {
        const result = classifyLoginQr(
            'https://nexiam.example.test/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
            origin,
        );
        expect(result).toEqual({
            kind: 'activation',
            href: 'https://nexiam.example.test/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
        });
    });

    it('preserves canonical applicant pass URL and bare-code entry shapes', () => {
        expect(
            classifyLoginQr(
                'https://nexiam.example.test/scan/applicant/Abc_123-x',
                origin,
            ).kind,
        ).toBe('applicant');
        expect(classifyLoginQr('Abc_123-x', origin)).toEqual({
            kind: 'applicant',
            href: 'https://nexiam.example.test/scan/applicant/Abc_123-x',
        });
    });

    it.each([
        'https://other.example.test/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
        'https://nexiam.example.test/staff/activate?next=https://other.example.test#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
        'https://nexiam.example.test/staff/activate#v=ABCDEFGH23&t=not-a-token',
        'https://nexiam.example.test/scan/applicant/Abc_123-x?next=/staff',
        'https://nexiam.example.test/scan/applicant/Abc_123-x#extra',
        'javascript:alert(1)',
    ])('rejects unsupported or mixed QR payload %s', (payload) => {
        expect(() => classifyLoginQr(payload, origin)).toThrow();
    });

    it('permits HTTP only on the exact loopback development origin', () => {
        expect(
            classifyLoginQr(
                'http://localhost/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
                'http://localhost/login',
            ).kind,
        ).toBe('activation');
        expect(() =>
            classifyLoginQr(
                'http://nexiam.example.test/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
                'http://nexiam.example.test/login',
            ),
        ).toThrow();
    });
});
