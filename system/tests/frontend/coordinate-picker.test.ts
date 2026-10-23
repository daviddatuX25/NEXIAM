// @vitest-environment jsdom

import { cleanup, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as leaflet from 'leaflet';
import CoordinatePicker from '../../resources/js/Components/Setup/CoordinatePicker.svelte';
const {
    mapMock,
    markerMock,
    tileLayerMock,
    iconMock,
    mergeOptionsMock,
    MockIconDefault,
} = vi.hoisted(() => {
    const map = {
        setView: vi.fn().mockReturnThis(),
        panTo: vi.fn().mockReturnThis(),
        getZoom: vi.fn().mockReturnValue(18),
        addLayer: vi.fn().mockReturnThis(),
        remove: vi.fn(),
        on: vi.fn().mockReturnThis(),
        off: vi.fn().mockReturnThis(),
    };

    const marker = {
        addTo: vi.fn().mockReturnThis(),
        setLatLng: vi.fn().mockReturnThis(),
        setIcon: vi.fn().mockReturnThis(),
        getLatLng: vi
            .fn()
            .mockReturnValue({ lat: 16.9339371, lng: 120.4448641 }),
        on: vi.fn().mockReturnThis(),
        remove: vi.fn(),
    };

    const tileLayer = {
        addTo: vi.fn().mockReturnThis(),
    };

    const mergeOptions = vi.fn();
    function MockDefault() {}
    MockDefault.mergeOptions = mergeOptions;
    MockDefault.prototype = {
        _getIconUrl: vi.fn(),
    };

    const icon = vi.fn((options) => ({ options, _isMockIcon: true }));

    return {
        mapMock: map,
        markerMock: marker,
        tileLayerMock: tileLayer,
        iconMock: icon,
        mergeOptionsMock: mergeOptions,
        MockIconDefault: MockDefault,
    };
});

vi.mock('leaflet', () => {
    const leaflet = {
        map: vi.fn(() => mapMock),
        tileLayer: vi.fn(() => tileLayerMock),
        marker: vi.fn(() => markerMock),
        icon: iconMock,
        Icon: {
            Default: MockIconDefault,
        },
    };
    return { ...leaflet, default: leaflet };
});
vi.mock('leaflet/dist/images/marker-icon.png', () => ({
    default: '/assets/marker-icon.png',
}));
vi.mock('leaflet/dist/images/marker-icon-2x.png', () => ({
    default: '/assets/marker-icon-2x.png',
}));
vi.mock('leaflet/dist/images/marker-shadow.png', () => ({
    default: '/assets/marker-shadow.png',
}));

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    cleanup();
});

describe('CoordinatePicker', () => {
    it('initializes map and configures Leaflet default marker icons', async () => {
        render(CoordinatePicker, {
            props: {
                latitude: 16.9339371,
                longitude: 120.4448641,
            },
        });

        await waitFor(() => {
            expect(mergeOptionsMock).toHaveBeenCalledWith(
                expect.objectContaining({
                    iconUrl: expect.stringContaining('marker-icon.png'),
                    iconRetinaUrl:
                        expect.stringContaining('marker-icon-2x.png'),
                    shadowUrl: expect.stringContaining('marker-shadow.png'),
                }),
            );
        });
    });

    it('creates marker with explicit icon and coordinates when latitude and longitude are set', async () => {
        render(CoordinatePicker, {
            props: {
                latitude: 16.9339371,
                longitude: 120.4448641,
            },
        });

        await waitFor(() => {
            expect(leaflet.marker).toHaveBeenCalledWith(
                [16.9339371, 120.4448641],
                expect.objectContaining({
                    draggable: true,
                    icon: expect.anything(),
                }),
            );
        });

        expect(iconMock).toHaveBeenCalledWith(
            expect.objectContaining({
                iconUrl: expect.stringContaining('marker-icon.png'),
                iconSize: [25, 41],
                iconAnchor: [12, 41],
            }),
        );
    });

    it('does not create marker when coordinates are null', async () => {
        render(CoordinatePicker, {
            props: {
                latitude: null,
                longitude: null,
            },
        });

        await waitFor(() => {
            expect(leaflet.map).toHaveBeenCalled();
        });

        expect(leaflet.marker).not.toHaveBeenCalled();
    });
});
