import { describe, expect, it } from 'vitest';
import {
    clusterLocalVenues,
    ContinuousRotationTracker,
    deriveMapViewport,
    deriveTurnInstruction,
    deriveWayfindingStage,
    extractDeviceHeading,
    filterGpsJitter,
    forwardAzimuthBearing,
    haversineDistanceMeters,
    relativeNeedleAngle,
    smoothAngle,
    type WayfindingStage,
} from '../../resources/js/lib/wayfinding-engine';

describe('wayfinding engine geodesics', () => {
    it('returns realistic positive distances for known locations', () => {
        expect(haversineDistanceMeters(0, 0, 0, 1)).toBeCloseTo(111_194.9, 0);

        const manilaToTagudinMeters = haversineDistanceMeters(14.5995, 120.9842, 16.9364, 120.4453);
        expect(manilaToTagudinMeters).toBeGreaterThan(250_000);
        expect(manilaToTagudinMeters).toBeLessThan(280_000);
    });

    it.each([
        ['north', 0, 0, 1, 0, 0],
        ['east', 0, 0, 0, 1, 90],
        ['south', 1, 0, 0, 0, 180],
        ['west', 0, 1, 0, 0, 270],
    ])('calculates a %s bearing', (_direction, lat1, lon1, lat2, lon2, expected) => {
        expect(forwardAzimuthBearing(lat1 as number, lon1 as number, lat2 as number, lon2 as number))
            .toBeCloseTo(expected as number, 8);
    });

    it('returns zero distance for identical points and normalizes the relative needle angle', () => {
        expect(haversineDistanceMeters(14.5, 120.9, 14.5, 120.9)).toBe(0);
        expect(relativeNeedleAngle(5, 355)).toBe(10);
        expect(relativeNeedleAngle(355, 5)).toBe(350);
    });
});

describe('turn instruction derivation', () => {
    it.each([
        [0, 'straight', 'Straight Ahead'],
        [45, 'slight_right', 'Bear Right'],
        [90, 'turn_right', 'Turn Right'],
        [180, 'turn_around', 'Turn Around'],
        [-90, 'turn_left', 'Turn Left'],
        [-45, 'slight_left', 'Bear Left'],
    ] as const)('maps %s degrees to the matching turn cue', (angle, action, text) => {
        expect(deriveTurnInstruction(angle)).toEqual({ action, text });
    });
});

describe('GPS jitter filtering', () => {
    it('keeps the previous coordinates when movement is below the minimum delta', () => {
        expect(filterGpsJitter(14.5, 120.9, 14.5, 120.90001, null)).toEqual({
            lat: 14.5,
            lng: 120.9,
            changed: false,
        });
    });

    it('accepts movement above the minimum delta', () => {
        expect(filterGpsJitter(14.5, 120.9, 14.5, 120.9001, null)).toEqual({
            lat: 14.5,
            lng: 120.9001,
            changed: true,
        });
    });
});

describe('wayfinding engine device heading', () => {
    function orientationEvent(values: Partial<DeviceOrientationEvent> & { webkitCompassHeading?: number }): DeviceOrientationEvent {
        return values as DeviceOrientationEvent;
    }

    it('prefers a valid iOS compass heading, including north, and normalizes values above 360', () => {
        expect(extractDeviceHeading(orientationEvent({ webkitCompassHeading: 0, alpha: null, beta: null, gamma: null }))).toBe(0);
        expect(extractDeviceHeading(orientationEvent({ webkitCompassHeading: 360, alpha: null, beta: null, gamma: null }))).toBe(0);
        expect(extractDeviceHeading(orientationEvent({ webkitCompassHeading: 450, alpha: null, beta: null, gamma: null }))).toBe(90);
    });

    it.each([
        ['North', 0, 0],
        ['East', 270, 90],
        ['South', 180, 180],
        ['West', 90, 270],
    ])('returns %s for a flat device orientation', (_direction, alpha, expected) => {
        expect(extractDeviceHeading(orientationEvent({ alpha, beta: 0, gamma: 0 }))).toBeCloseTo(expected as number, 8);
    });

    it('returns a finite heading for a tilted device', () => {
        const heading = extractDeviceHeading(orientationEvent({ alpha: 45, beta: 30, gamma: 10 }));

        expect(heading).not.toBeNull();
        expect(Number.isFinite(heading)).toBe(true);
    });

    it('returns null when the projected screen-top vector is vertical', () => {
        expect(extractDeviceHeading(orientationEvent({ alpha: 0, beta: 90, gamma: 0 }))).toBeNull();
    });

    it('returns null when Euler orientation values are unavailable', () => {
        expect(extractDeviceHeading(orientationEvent({ alpha: null, beta: null, gamma: null }))).toBeNull();
    });
});

describe('wayfinding angle smoothing', () => {
    it('smooths across north along the short arc', () => {
        expect(smoothAngle(359, 1)).toBeCloseTo(359.5, 8);
        expect(smoothAngle(1, 359)).toBeCloseTo(0.5, 8);
    });

    it('tracks continuous rotation forward when crossing north', () => {
        const tracker = new ContinuousRotationTracker();
        expect(tracker.update(355, 1)).toBe(355);
        expect(tracker.update(5, 1)).toBe(365);
        expect(tracker.currentDisplayedDeg).toBe(365);
    });
});

describe('wayfinding stage derivation', () => {
    it.each([
        [150, 5, null, 'overview'],
        [50, 5, null, 'walking'],
        [12, 5, null, 'arrived'],
        [35, 31, null, 'walking'],
        [18, 26, null, 'walking'],
        [18, 25, null, 'arrived'],
        [36, 31, null, 'walking'],
        [18, null, null, 'arrived'],
        [null, 5, null, 'overview'],
    ] as Array<[number | null, number | null, WayfindingStage | null, WayfindingStage]>) (
        'derives %s meters at accuracy %s as %s',
        (distance, accuracy, manualOverride, expected) => {
            expect(deriveWayfindingStage(distance, accuracy, manualOverride)).toBe(expected);
        },
    );

    it.each(['overview', 'walking'] as WayfindingStage[])('honors the %s manual override', (override) => {
        expect(deriveWayfindingStage(500, 5, override)).toBe(override);
    });

    it('does not let a manual arrival override bypass proximity or weak GPS checks', () => {
        expect(deriveWayfindingStage(500, 5, 'arrived')).toBe('overview');
        expect(deriveWayfindingStage(18, 26, 'arrived')).toBe('walking');
        expect(deriveWayfindingStage(18, 20, 'arrived')).toBe('arrived');
});
});

describe('local venue clustering', () => {
    it('keeps nearby campus venues and excludes a distant regional outlier', () => {
        const venues = [
            { name: 'Campus entrance', latitude: 16.9364, longitude: 120.4453 },
            { name: 'Campus office', latitude: 16.9372, longitude: 120.4453 },
            { name: 'Regional venue', latitude: 16.04, longitude: 120.35 },
        ];

        expect(clusterLocalVenues(venues, { latitude: 16.9364, longitude: 120.4453 }))
            .toEqual(venues.slice(0, 2));
        expect(haversineDistanceMeters(16.9364, 120.4453, 16.9372, 120.4453)).toBeLessThan(200);
    });

    it('uses a local reference when the focal point is null and handles empty input', () => {
        const venues = [
            { latitude: 16.9364, longitude: 120.4453 },
            { latitude: 16.9372, longitude: 120.4453 },
            { latitude: 16.04, longitude: 120.35 },
            { latitude: null, longitude: 120.4453 },
        ];

        expect(clusterLocalVenues(venues, null)).toEqual(venues.slice(0, 2));
        expect(clusterLocalVenues([])).toEqual([]);
    });
});

describe('map viewport derivation', () => {
    it('snaps to the destination in doorstep mode within 18 meters', () => {
        expect(deriveMapViewport(16.936, 120.445, 16.9364, 120.4453, 18)).toEqual({
            mode: 'doorstep',
            center: [16.9364, 120.4453],
            bounds: null,
            zoom: 19,
        });
    });

    it('frames the user and target in navigating mode', () => {
        expect(deriveMapViewport(16.9, 120.4, 17, 120.5, 100)).toEqual({
            mode: 'navigating',
            center: [16.95, 120.45],
            bounds: [[16.9, 120.4], [17, 120.5]],
            zoom: 18,
        });
    });

    it('centers on the target when user coordinates are unavailable', () => {
        expect(deriveMapViewport(null, null, 16.9364, 120.4453, null)).toEqual({
            mode: 'target_only',
            center: [16.9364, 120.4453],
            bounds: null,
            zoom: 18,
        });
    });
});
