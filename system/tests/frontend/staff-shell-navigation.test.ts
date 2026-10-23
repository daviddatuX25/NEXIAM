import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

describe('Staff and applicant shell navigation ergonomics', () => {
    const staffLayout = readFileSync(new URL('../../resources/js/Pages/Staff/StaffLayout.svelte', import.meta.url), 'utf8');
    const applicantLayout = readFileSync(new URL('../../resources/js/Pages/Applicant/ApplicantLayout.svelte', import.meta.url), 'utf8');
    const homeLayout = readFileSync(new URL('../../resources/js/Layouts/HomeLayout.svelte', import.meta.url), 'utf8');

    describe('StaffLayout mobile header and direct logout', () => {
        it('removes profile icon from mobile header to prevent visual cramping', () => {
            // Profile icon should not exist in the mobile header (md:hidden)
            expect(staffLayout).not.toMatch(/href=['"]\/staff\/profile['"][^>]*md:hidden/);
            // Profile remains in the sidebar drawer
            expect(staffLayout).toMatch(/href=['"]\/staff\/profile['"]/);
        });

        it('replaces three dots menu with direct logout icon in sidebar footer', () => {
            // Three dots (MoreVertical) and popup menu must not exist
            expect(staffLayout).not.toContain('MoreVertical');
            expect(staffLayout).not.toContain('id="staff-account-menu"');
            expect(staffLayout).not.toContain('userDropdownOpen');

            // Profile link is direct and logout button has direct LogOut icon
            expect(staffLayout).toContain('href="/staff/profile"');
            expect(staffLayout).toContain('promptLogout');
            expect(staffLayout).toMatch(/<LogOut\b/);
        });

        it('provides accessible confirmation dialog before signing out', () => {
            expect(staffLayout).toContain('logoutConfirmOpen');
            expect(staffLayout).toContain('Sign out of staff workspace?');
            expect(staffLayout).toContain('confirmLogout');
        });

        it('allows unrestricted, native in-site navigation without departure popups', () => {
            expect(staffLayout).not.toContain('leaveWorkspaceDialogOpen');
            expect(staffLayout).not.toContain('Leave Staff Workspace?');
            expect(staffLayout).not.toContain('router.on(\'before\'');
        });

        it('removes redundant NEXIAM Engine version badge from navigation footer', () => {
            expect(staffLayout).not.toContain('NEXIAM Engine');
        });
    });

    describe('ApplicantLayout logout confirmation', () => {
        it('requires confirmation before logging out of applicant portal', () => {
            expect(applicantLayout).toContain('logoutConfirmOpen');
            expect(applicantLayout).toContain('Sign out of applicant portal?');
            expect(applicantLayout).toContain('promptLogout');
            expect(applicantLayout).toContain('confirmLogout');
        });
    });

    describe('HomeLayout subtle floating return button', () => {
        it('displays subtle floating middle-bottom return button for authenticated staff and applicants without intrusive signout design', () => {
            expect(homeLayout).toContain('isStaff');
            expect(homeLayout).toContain('Return to Staff Workspace');
            expect(homeLayout).toContain('Return to Portal');
            expect(homeLayout).toMatch(/fixed bottom-5 left-1\/2 -translate-x-1\/2/);
            expect(homeLayout).toMatch(/animate-ping/);
            expect(homeLayout).not.toContain('staffLogoutConfirmOpen');
            expect(homeLayout).not.toContain('Sign out of staff account?');
        });
    });

    describe('IdleTimeoutGuard overlay and audio ergonomics', () => {
        const idleGuard = readFileSync(new URL('../../resources/js/Components/kit/IdleTimeoutGuard.svelte', import.meta.url), 'utf8');

        it('does not nest redundant outer Dialog.Portal or Dialog.Overlay to avoid double-dimming backdrop pop', () => {
            // Dialog.Content already supplies DialogPortal and Dialog.Overlay
            expect(idleGuard).not.toContain('<Dialog.Portal>');
            expect(idleGuard).not.toContain('<Dialog.Overlay />');
            expect(idleGuard).toContain('<Dialog.Root open={true}');
            expect(idleGuard).toContain('<Dialog.Content');
        });
    });
});
