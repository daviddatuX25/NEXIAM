import { describe, expect, it } from 'vitest';
import { formatXiamPackTitle } from '../../resources/js/lib/xiam-context';

describe('formatXiamPackTitle', () => {
    it.each([
        ['staff.workspace.home@1', 'Staff Workspace'],
        ['staff.setup.readiness@1', 'Institutional Setup'],
        ['staff.knowledge.sources@1', 'Knowledge Governance'],
        ['staff.exams.sessions.index@1', 'Exam Sessions'],
        ['staff.exams.sessions.show@1', 'Exam Session Detail'],
        ['applicant.schedule@1', 'Exam Slot Schedule'],
    ])('formats %s as %s', (packKey, title) => {
        expect(formatXiamPackTitle(packKey)).toBe(title);
    });

    it('strips the version and formats unknown pack keys', () => {
        expect(formatXiamPackTitle('staff.new.feature-area@3')).toBe(
            'Staff New Feature Area',
        );
    });

    it.each([null, undefined, ''])('uses the fallback for %s', (packKey) => {
        expect(formatXiamPackTitle(packKey, 'Current Page')).toBe('Current Page');
    });
});
