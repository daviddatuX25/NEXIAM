// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, within } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, expect, it, vi } from 'vitest';
import PreferencesMenu from '../../resources/js/Components/kit/PreferencesMenu.svelte';
import { walkthroughCoordinator, walkthroughLaunchBlocked } from '../../resources/js/lib/walkthrough/coordinator';

const push = vi.hoisted(() => ({
    getCurrentSubscription: vi.fn(),
    subscribeToPush: vi.fn(),
    unsubscribeFromPush: vi.fn(),
}));
vi.mock('@/lib/push-notifications', () => ({
    ...push,
    isPushSupported: () => true,
    isIosSafari: () => false,
    getNotificationPermission: () => 'granted',
}));
vi.mock('@/lib/toast', () => ({ success: vi.fn(), error: vi.fn() }));

afterEach(() => { cleanup(); vi.clearAllMocks(); });

it('keeps applicant alerts enabled when an older opening lookup finishes after enabling', async () => {
    const { promise, resolve: finishLookup } = Promise.withResolvers<null>();
    push.getCurrentSubscription.mockReturnValue(promise);
    push.subscribeToPush.mockResolvedValue({ success: true });
    render(PreferencesMenu);
    await fireEvent.click(screen.getByRole('button', { name: 'Accessibility and display preferences' }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Turn on alerts', exact: true }));
    await screen.findByRole('button', { name: 'Turn off alerts', exact: true });
    finishLookup(null);
    await tick();
    expect(screen.queryByRole('button', { name: 'Turn off alerts', exact: true })).not.toBeNull();
    expect(screen.queryByText(/^Off\. Turn on/)).toBeNull();
});

it('changes automatic guidance from the shared menu and keeps the choice on remount', async () => {
    push.getCurrentSubscription.mockResolvedValue(null);
    walkthroughCoordinator.setPlaybackMode('auto');
    const menu = render(PreferencesMenu);
    await fireEvent.click(screen.getByRole('button', { name: 'Accessibility and display preferences' }));
    const modes = within(await screen.findByRole('group', { name: 'Xiam walkthroughs' }));
    const popover = screen.getByRole('group', { name: 'Xiam walkthroughs' }).closest<HTMLElement>('[data-slot="popover-content"]')!;
    vi.spyOn(popover, 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 0, 336, 600));
    expect(walkthroughLaunchBlocked()).toBe(true);
    await fireEvent.click(modes.getByRole('button', { name: 'Off', exact: true }));
    await tick();
    expect(modes.getByRole('button', { name: 'Off', exact: true }).getAttribute('aria-pressed')).toBe('true');
    expect(walkthroughCoordinator.beginAutomatic('page:unseen-preferences-test', 1)).toBeNull();
    menu.unmount();

    render(PreferencesMenu);
    await fireEvent.click(screen.getByRole('button', { name: 'Accessibility and display preferences' }));
    const reopened = within(await screen.findByRole('group', { name: 'Xiam walkthroughs' }));
    expect(reopened.getByRole('button', { name: 'Off', exact: true }).getAttribute('aria-pressed')).toBe('true');
    await fireEvent.click(reopened.getByRole('button', { name: 'Always', exact: true }));
    await tick();
    expect(walkthroughCoordinator.getPlaybackMode()).toBe('always');
    expect(reopened.getByRole('button', { name: 'Always', exact: true }).getAttribute('aria-pressed')).toBe('true');
    walkthroughCoordinator.setPlaybackMode('auto');
});
