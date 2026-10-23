export type WayfindingStage = 'overview' | 'walking' | 'arrived';

const EARTH_RADIUS_METERS = 6_371_000;
const DEGREES_TO_RADIANS = Math.PI / 180;
const RADIANS_TO_DEGREES = 180 / Math.PI;

function normalizeDegrees(degrees: number): number {
    return ((degrees % 360) + 360) % 360;
}

function shortestAngleDelta(current: number, target: number): number {
    return ((normalizeDegrees(target) - normalizeDegrees(current) + 540) % 360) - 180;
}
export type TurnInstructionAction =
    | 'straight'
    | 'slight_right'
    | 'turn_right'
    | 'turn_around'
    | 'turn_left'
    | 'slight_left';

export function deriveTurnInstruction(relativeAngle: number): { action: TurnInstructionAction; text: string } {
    const angle = ((relativeAngle % 360) + 540) % 360 - 180;

    if (angle >= -25 && angle <= 25) {
        return { action: 'straight', text: 'Straight Ahead' };
    }

    if (angle > 25 && angle <= 65) {
        return { action: 'slight_right', text: 'Bear Right' };
    }

    if (angle > 65 && angle <= 115) {
        return { action: 'turn_right', text: 'Turn Right' };
    }

    if (angle > 115 || angle < -115) {
        return { action: 'turn_around', text: 'Turn Around' };
    }

    if (angle >= -115 && angle < -65) {
        return { action: 'turn_left', text: 'Turn Left' };
    }

    return { action: 'slight_left', text: 'Bear Left' };
}

export function filterGpsJitter(
    prevLat: number | null,
    prevLng: number | null,
    nextLat: number,
    nextLng: number,
    accuracy: number | null,
    minDeltaMeters = 4,
): { lat: number; lng: number; changed: boolean } {
    if (prevLat === null || prevLng === null) {
        return { lat: nextLat, lng: nextLng, changed: true };
    }

    const distance = haversineDistanceMeters(prevLat, prevLng, nextLat, nextLng);
    if (
        distance < minDeltaMeters
        || (accuracy !== null && accuracy > 20 && distance < Math.min(accuracy * 0.35, 12))
    ) {
        return { lat: prevLat, lng: prevLng, changed: false };
    }

    return { lat: nextLat, lng: nextLng, changed: true };
}


export function haversineDistanceMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const latitude1 = lat1 * DEGREES_TO_RADIANS;
    const latitude2 = lat2 * DEGREES_TO_RADIANS;
    const latitudeDelta = (lat2 - lat1) * DEGREES_TO_RADIANS;
    const longitudeDelta = (lon2 - lon1) * DEGREES_TO_RADIANS;

    const haversine = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(latitude1) * Math.cos(latitude2) * Math.sin(longitudeDelta / 2) ** 2;
    const centralAngle = 2 * Math.atan2(Math.sqrt(Math.min(1, haversine)), Math.sqrt(Math.max(0, 1 - haversine)));

    return EARTH_RADIUS_METERS * centralAngle;
}
export function clusterLocalVenues<T extends { latitude?: number | null; longitude?: number | null }>(
    venues: T[],
    focalPoint?: { latitude: number; longitude: number } | null,
    maxRadiusMeters = 8_000,
): T[] {
    const validVenues = venues.filter((venue) => venue.latitude != null && venue.longitude != null);

    if (validVenues.length === 0) {
        return validVenues;
    }

    const center = focalPoint ?? {
        latitude: validVenues[0].latitude!,
        longitude: validVenues[0].longitude!,
    };
    const radiusMeters = focalPoint === null || focalPoint === undefined ? 15_000 : maxRadiusMeters;
    const localVenues = validVenues.filter((venue) => (
        haversineDistanceMeters(center.latitude, center.longitude, venue.latitude!, venue.longitude!) <= radiusMeters
    ));

    return localVenues.length > 0 ? localVenues : validVenues;
}


export function forwardAzimuthBearing(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const latitude1 = lat1 * DEGREES_TO_RADIANS;
    const latitude2 = lat2 * DEGREES_TO_RADIANS;
    const longitudeDelta = (lon2 - lon1) * DEGREES_TO_RADIANS;
    const y = Math.sin(longitudeDelta) * Math.cos(latitude2);
    const x = Math.cos(latitude1) * Math.sin(latitude2)
        - Math.sin(latitude1) * Math.cos(latitude2) * Math.cos(longitudeDelta);

    return normalizeDegrees(Math.atan2(y, x) * RADIANS_TO_DEGREES);
}

export function extractDeviceHeading(event: DeviceOrientationEvent): number | null {
    const orientation = event as DeviceOrientationEvent & { webkitCompassHeading?: number };
    const webkitHeading = orientation.webkitCompassHeading;

    if (typeof webkitHeading === 'number' && Number.isFinite(webkitHeading) && webkitHeading >= 0) {
        return normalizeDegrees(webkitHeading);
    }

    const { alpha, beta, gamma } = event;

    if (alpha === null || beta === null || gamma === null
        || !Number.isFinite(alpha) || !Number.isFinite(beta) || !Number.isFinite(gamma)) {
        return null;
    }

    const a = alpha * DEGREES_TO_RADIANS;
    const b = beta * DEGREES_TO_RADIANS;
    const g = gamma * DEGREES_TO_RADIANS;

    const vE = -Math.sin(a) * Math.cos(b) - Math.cos(a) * Math.sin(g) * Math.sin(b);
    const vN = Math.cos(a) * Math.cos(b) - Math.sin(a) * Math.sin(g) * Math.sin(b);

    if (Math.hypot(vE, vN) < 1e-6) {
        return null;
    }

    return normalizeDegrees(Math.atan2(vE, vN) * RADIANS_TO_DEGREES);
}

export function smoothAngle(current: number, target: number, alpha = 0.25): number {
    const weight = Math.max(0, Math.min(1, alpha));

    return normalizeDegrees(current + shortestAngleDelta(current, target) * weight);
}

export class ContinuousRotationTracker {
    currentDisplayedDeg = 0;
    private isInitialized = false;

    update(targetDeg: number, alpha = 0.25): number {
        const normalizedTarget = normalizeDegrees(targetDeg);

        if (!this.isInitialized) {
            this.currentDisplayedDeg = normalizedTarget;
            this.isInitialized = true;
            return this.currentDisplayedDeg;
        }

        const weight = Math.max(0, Math.min(1, alpha));
        this.currentDisplayedDeg += shortestAngleDelta(this.currentDisplayedDeg, normalizedTarget) * weight;

        return this.currentDisplayedDeg;
    }
}

export function deriveWayfindingStage(
    distanceMeters: number | null,
    gpsAccuracy: number | null,
    manualOverride: WayfindingStage | null,
): WayfindingStage {
    if (manualOverride !== null && manualOverride !== 'arrived') {
        return manualOverride;
    }

    if (distanceMeters === null) {
        return 'overview';
    }

    if (distanceMeters <= 18 && (gpsAccuracy === null || gpsAccuracy <= 25)) {
        return 'arrived';
    }

    if (distanceMeters <= 100) {
        return 'walking';
    }

    return 'overview';
}

export function relativeNeedleAngle(bearing: number, heading: number): number {
    return normalizeDegrees(bearing - heading);
}
export function deriveMapViewport(
    userLat: number | null,
    userLng: number | null,
    targetLat: number | null,
    targetLng: number | null,
    distanceMeters: number | null,
): {
    mode: 'doorstep' | 'navigating' | 'target_only' | 'fallback';
    center: [number, number] | null;
    bounds: [[number, number], [number, number]] | null;
    zoom: number;
} {
    if (targetLat === null || targetLng === null) {
        return {
            mode: 'fallback',
            center: userLat !== null && userLng !== null ? [userLat, userLng] : null,
            bounds: null,
            zoom: 16,
        };
    }

    if (distanceMeters !== null && distanceMeters <= 18) {
        return {
            mode: 'doorstep',
            center: [targetLat, targetLng],
            bounds: null,
            zoom: 19,
        };
    }

    if (userLat !== null && userLng !== null) {
        return {
            mode: 'navigating',
            center: [(userLat + targetLat) / 2, (userLng + targetLng) / 2],
            bounds: [
                [Math.min(userLat, targetLat), Math.min(userLng, targetLng)],
                [Math.max(userLat, targetLat), Math.max(userLng, targetLng)],
            ],
            zoom: distanceMeters !== null && distanceMeters <= 60
                ? 19
                : distanceMeters !== null && distanceMeters <= 150
                    ? 18
                    : 17,
        };
    }

    return {
        mode: 'target_only',
        center: [targetLat, targetLng],
        bounds: null,
        zoom: 18,
    };
}
