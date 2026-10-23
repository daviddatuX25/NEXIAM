// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Leaflet from 'leaflet';
import VenueCompassHud from '../../resources/js/Components/Wayfinding/VenueCompassHud.svelte';
vi.mock('leaflet', () => {
    const map = {
        setView: vi.fn().mockReturnThis(),
        fitBounds: vi.fn().mockReturnThis(),
        addLayer: vi.fn().mockReturnThis(),
        remove: vi.fn(),
        on: vi.fn().mockReturnThis(),
        off: vi.fn().mockReturnThis(),
        invalidateSize: vi.fn().mockReturnThis(),
    };
    const layer = {
        addTo: vi.fn().mockReturnThis(),
        bindPopup: vi.fn().mockReturnThis(),
        on: vi.fn().mockReturnThis(),
        setLatLng: vi.fn().mockReturnThis(),
        remove: vi.fn(),
    };
    const route = {
        addTo: vi.fn().mockReturnThis(),
        setLatLngs: vi.fn().mockReturnThis(),
        remove: vi.fn(),
    };
    const leaflet = {
        map: vi.fn(() => map),
        tileLayer: vi.fn(() => layer),
        marker: vi.fn(() => layer),
        polyline: vi.fn(() => route),
        divIcon: vi.fn(() => ({})),
        icon: vi.fn(() => ({})),
    };

    return { ...leaflet, default: leaflet };
});


beforeEach(() => {
    vi.clearAllMocks();
    window.matchMedia = vi.fn().mockReturnValue({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    });
    if (typeof HTMLElement.prototype.animate !== 'function') {
        HTMLElement.prototype.animate = vi.fn().mockReturnValue({
            finished: Promise.resolve(),
            cancel: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });
    }
});

const venues = [
    { id: 1, code: 'ITBR-106', name: 'Guidance Office', building_name: 'IT Building' },
    { id: 2, code: 'LIB-201', name: 'Library', building_name: 'Library' },
];

afterEach(() => {
    cleanup();
    localStorage.clear();
    vi.unstubAllGlobals();
});

describe('VenueCompassHud destinations', () => {
    it('opens the requested department office from the loaded directory without selecting another venue', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => venues }));
        render(VenueCompassHud, { open: true, roomId: 2, onClose: vi.fn() });

        expect(await screen.findByRole('dialog', { name: 'Library', exact: true })).toBeTruthy();
        expect(screen.queryByRole('dialog', { name: 'Guidance Office', exact: true })).toBeNull();
        expect(screen.getByRole('button', { name: 'Back to Map' })).toBeTruthy();
    });

    it('keeps the directory available when a requested office is no longer listed', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => venues }));
        render(VenueCompassHud, { open: true, roomId: 999, onClose: vi.fn() });

        expect(await screen.findByRole('status')).toHaveProperty('textContent', expect.stringContaining('not available'));
        expect(screen.getByRole('dialog', { name: 'Campus Compass', exact: true })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Back to Map' })).toBeNull();
    });

    it('renders the locked venue name and code', () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, { open: true, venue: venues[0], onClose: vi.fn() });

        expect(screen.getByText('Guidance Office')).toBeTruthy();
        expect(screen.getByText('IT Building')).toBeTruthy();
    });

    it('shows a destination directory without preselecting a venue', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ json: async () => venues });
        vi.stubGlobal('fetch', fetchMock);
        render(VenueCompassHud, { open: true, venue: null, onClose: vi.fn() });

        await waitFor(() => expect(fetchMock).toHaveBeenCalledWith('/api/wayfinding/venues'));
        expect(screen.getAllByText('Campus Compass').length).toBeGreaterThan(0);
        expect(screen.getByText('Select a Destination')).toBeTruthy();
        expect(await screen.findByRole('button', { name: /Guidance Office.*ITBR-106/ })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Library.*LIB-201/ })).toBeTruthy();

        const map = await screen.findByRole('application', { name: 'Interactive campus map' });
        await fireEvent.click(screen.getByRole('button', { name: /Library.*LIB-201/ }));
        expect(screen.getAllByText('Library').length).toBeGreaterThan(0);
        expect(screen.getByRole('button', { name: 'Back to Map' })).toBeTruthy();
        expect(screen.getByRole('application', { name: 'Interactive campus map' })).toBe(map);
    });
    it('navigates back to map view using Back to Map button', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => venues }));
        render(VenueCompassHud, { open: true, venue: null, onClose: vi.fn() });

        const map = await screen.findByRole('application', { name: 'Interactive campus map' });
        await fireEvent.click(await screen.findByRole('button', { name: /Library.*LIB-201/ }));
        await fireEvent.click(screen.getByRole('button', { name: 'Back to Map' }));

        expect(screen.getByText('Select a Destination')).toBeTruthy();
        expect(screen.getByRole('button', { name: /Library.*LIB-201/ })).toBeTruthy();
        expect(screen.getByRole('application', { name: 'Interactive campus map' })).toBe(map);
    });
    it('renders a dynamic route polyline when user and destination coordinates are active', async () => {
        localStorage.setItem('nexiam_wayfinding_consent_v1', 'granted');
        vi.stubGlobal('DeviceOrientationEvent', class {});
        vi.stubGlobal('navigator', {
            geolocation: {
                watchPosition: vi.fn((onSuccess: (position: { coords: { latitude: number; longitude: number; accuracy: number } }) => void) => {
                    onSuccess({ coords: { latitude: 14, longitude: 120, accuracy: 5 } });
                    return 1;
                }),
                clearWatch: vi.fn(),
            },
        });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, {
            open: true,
            venue: { ...venues[0], latitude: 14.001, longitude: 120.001 },
            onClose: vi.fn(),
        });

        await waitFor(() => expect(vi.mocked(Leaflet.polyline)).toHaveBeenCalledWith(
            [[14, 120], [14.001, 120.001]],
            expect.objectContaining({ dashArray: '6, 8' }),
        ));
    });

    it('switches to doorstep view when distance is within 18m', async () => {
        localStorage.setItem('nexiam_wayfinding_consent_v1', 'granted');
        vi.stubGlobal('DeviceOrientationEvent', class {});
        vi.stubGlobal('navigator', {
            geolocation: {
                watchPosition: vi.fn((onSuccess: (position: { coords: { latitude: number; longitude: number; accuracy: number } }) => void) => {
                    onSuccess({ coords: { latitude: 14, longitude: 120, accuracy: 5 } });
                    return 1;
                }),
                clearWatch: vi.fn(),
            },
        });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, {
            open: true,
            venue: { ...venues[0], latitude: 14, longitude: 120 },
            onClose: vi.fn(),
        });

        expect(await screen.findByText('Near Venue')).toBeTruthy();
        expect(screen.getByText('GPS places you near the entrance! Match the door photo and sign before checking in.')).toBeTruthy();
        await waitFor(() => expect(vi.mocked(Leaflet.map)).toHaveBeenCalled());
        await waitFor(() => expect(vi.mocked(Leaflet.map).mock.results[0]?.value.setView).toHaveBeenCalledWith([14, 120], 19));
    });

    it('opens expanded door photo lightbox when Expand Photo is clicked', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, {
            open: true,
            venue: { ...venues[0], photo_url: '/entrance.jpg' },
            onClose: vi.fn(),
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Expand door photo' }));
        expect(screen.getByRole('dialog', { name: /entrance photo/i })).toBeTruthy();
    });

    it('reports unavailable GPS instead of claiming ON TRACK or arrival', async () => {
        localStorage.setItem('nexiam_wayfinding_consent_v1', 'granted');
        vi.stubGlobal('navigator', { geolocation: undefined });
        vi.stubGlobal('DeviceOrientationEvent', class {});
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, { open: true, venue: venues[0], onClose: vi.fn() });

        expect(screen.getAllByText('GPS Unavailable').length).toBeGreaterThan(0);
        expect(screen.queryByText('⚡ ON TRACK')).toBeNull();
        expect(screen.queryByText(/You’ve arrived|You've arrived/)).toBeNull();
        expect(screen.getByText('Location is unavailable. You can still use the entrance photo and landmark directions.')).toBeTruthy();
        const header = screen.getByRole('dialog').querySelector('header');
        expect(header).not.toBeNull();
        expect(header?.textContent).toContain('Back to Map');
        expect(header?.textContent).toContain('Guidance Office');
        expect(within(header as HTMLElement).getByRole('button', { name: 'Close wayfinding' })).toBeTruthy();
        expect(within(header as HTMLElement).queryByText('GPS Unavailable')).toBeNull();
    });

    it('labels weak GPS honestly and avoids arrival claims', async () => {
        localStorage.setItem('nexiam_wayfinding_consent_v1', 'granted');
        vi.stubGlobal('DeviceOrientationEvent', class {});
        vi.stubGlobal('navigator', {
            geolocation: {
                watchPosition: vi.fn((onSuccess: (position: { coords: { latitude: number; longitude: number; accuracy: number } }) => void) => {
                    onSuccess({ coords: { latitude: 14, longitude: 120, accuracy: 30 } });
                    return 1;
                }),
                clearWatch: vi.fn(),
            },
        });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, {
            open: true,
            venue: { ...venues[0], latitude: 14, longitude: 120 },
            onClose: vi.fn(),
        });

        expect(screen.getAllByText('Weak GPS (±30m)').length).toBeGreaterThan(0);
        expect(screen.getByText('GPS is too imprecise indoors for reliable direction. Use the entrance photo and landmarks.')).toBeTruthy();
        expect(screen.queryByText(/GPS places you near the entrance|You’ve arrived|You've arrived/)).toBeNull();
    });

    it('keeps the HUD open in Manual Landmark Mode after declining consent', async () => {
        const onClose = vi.fn();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, { open: true, venue: venues[0], onClose });

        await fireEvent.click(screen.getByRole('button', { name: /Browse Manually/i }));

        expect(onClose).not.toHaveBeenCalled();
        expect(screen.getAllByText('Manual Landmark Mode').length).toBeGreaterThan(0);
        expect(screen.getByText('Here is what the entrance looks like so you can spot it when you arrive.')).toBeTruthy();
    });
    it('map container remains mounted during venue navigation', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => venues }));
        render(VenueCompassHud, { open: true, venue: null, onClose: vi.fn() });

        const map = await screen.findByRole('application', { name: 'Interactive campus map' });
        await fireEvent.click(await screen.findByRole('button', { name: /Library.*LIB-201/ }));

        expect(screen.getByRole('application', { name: 'Interactive campus map' })).toBe(map);
        expect(screen.getByRole('button', { name: 'Back to Map' })).toBeTruthy();
    });
    it('toggles map orientation between heads up and north up', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, { open: true, venue: venues[0], onClose: vi.fn() });

        const orientationToggle = await screen.findByRole('button', { name: /heads up|north up/i });
        const initialLabel = orientationToggle.getAttribute('aria-label') ?? orientationToggle.textContent ?? '';
        await fireEvent.click(orientationToggle);

        const updatedToggle = screen.getByRole('button', { name: /heads up|north up/i });
        const updatedLabel = updatedToggle.getAttribute('aria-label') ?? updatedToggle.textContent ?? '';
        expect(updatedLabel).not.toBe(initialLabel);
        expect(updatedLabel).toMatch(/heads up|north up/i);
    });

    it('displays turn instruction guidance based on relative angle', async () => {
        localStorage.setItem('nexiam_wayfinding_consent_v1', 'granted');
        vi.stubGlobal('DeviceOrientationEvent', class {});
        vi.stubGlobal('navigator', {
            geolocation: {
                watchPosition: vi.fn((onSuccess: (position: { coords: { latitude: number; longitude: number; accuracy: number } }) => void) => {
                    onSuccess({ coords: { latitude: 14, longitude: 120, accuracy: 5 } });
                    return 1;
                }),
                clearWatch: vi.fn(),
            },
        });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, {
            open: true,
            venue: { ...venues[0], latitude: 14.001, longitude: 120 },
            onClose: vi.fn(),
        });

        expect(await screen.findByText(/Straight Ahead|Bear Right|Turn Right|Turn Around|Turn Left|Bear Left/i)).toBeTruthy();
        expect(screen.getByLabelText(/Compass direction/i)).toBeTruthy();
        expect(screen.getByText('N')).toBeTruthy();
    });

    it('allows manual location adjustment on map tap', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => [] }));
        render(VenueCompassHud, { open: true, venue: venues[0], onClose: vi.fn() });

        await fireEvent.click(await screen.findByRole('button', { name: /adjust my location/i }));

        await waitFor(() => expect(vi.mocked(Leaflet.map)).toHaveBeenCalled());
        const map = vi.mocked(Leaflet.map).mock.results.at(-1)?.value;
        const mapClick = vi.mocked(map.on).mock.calls.find(([eventName]) => eventName === 'click')?.[1] as
            | ((event: { latlng: { lat: number; lng: number } }) => void)
            | undefined;
        expect(mapClick).toBeTypeOf('function');

        mapClick?.({ latlng: { lat: 14.123, lng: 120.456 } });

        await waitFor(() => expect(vi.mocked(Leaflet.marker)).toHaveBeenCalledWith(
            [14.123, 120.456],
            expect.objectContaining({ title: 'Your location' }),
        ));
    });
});
