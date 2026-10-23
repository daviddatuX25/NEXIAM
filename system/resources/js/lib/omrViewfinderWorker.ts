export type ViewfinderPoint = { x: number; y: number };

/** Sensor-frame coordinates in clockwise TL, TR, BR, BL order, normalized to [0, 1]. */
export type ViewfinderQuad = [
    ViewfinderPoint,
    ViewfinderPoint,
    ViewfinderPoint,
    ViewfinderPoint,
];

export type ViewfinderGates = {
    in_bounds: boolean;
    angle_ok: boolean;
    steady_ok: boolean;
    lighting_ok: boolean;
};

export type ViewfinderRequest = {
    capturedAt: number;
    type: 'analyze';
    generation: number;
    frameId: number;
    bitmap: ImageBitmap;
    expectedAspectRatio: number;
};

export type ViewfinderGeometry = {
    observedAspectRatio: number;
    expectedAspectRatio: number;
    maxEdgeTiltRadians: number;
};

export type ViewfinderResult = {
    type: 'analysis';
    generation: number;
    frameId: number;
    capturedAt?: number;
    quad: ViewfinderQuad | null;
    gates: ViewfinderGates;
    geometry?: ViewfinderGeometry | null;
};

const MAX_EDGE_TILT_RADIANS = Math.PI / 15;
const ASPECT_RATIO_TOLERANCE = { min: 0.75, max: 1.25 } as const;

/** Gives orientation advice only when the detected shape fits the reciprocal sheet ratio. */
export function viewfinderFramingGuidance(
    result: Pick<ViewfinderResult, 'gates' | 'geometry'>,
): string[] {
    const guidance: string[] = [];

    if (!result.gates.in_bounds) {
        guidance.push('Move the whole sheet inside the frame.');
    }

    if (!result.gates.angle_ok) {
        const geometry = result.geometry;
        const expected = geometry?.expectedAspectRatio;
        const observed = geometry?.observedAspectRatio;
        const tilt = geometry?.maxEdgeTiltRadians;
        const expectedOrientation =
            expected !== undefined && expected < 1 ? 'portrait' : 'landscape';
        const inverseRatio = expected !== undefined ? 1 / expected : NaN;
        const inverseFit =
            observed !== undefined ? observed / inverseRatio : NaN;
        const standardFit =
            observed !== undefined &&
            expected !== undefined &&
            observed / expected >= ASPECT_RATIO_TOLERANCE.min &&
            observed / expected <= ASPECT_RATIO_TOLERANCE.max;
        const reciprocalFit =
            expected !== undefined &&
            Number.isFinite(expected) &&
            expected > 0 &&
            observed !== undefined &&
            Number.isFinite(observed) &&
            observed > 0 &&
            Number.isFinite(inverseFit) &&
            inverseFit >= ASPECT_RATIO_TOLERANCE.min &&
            inverseFit <= ASPECT_RATIO_TOLERANCE.max;
        const edgesSquareToCamera =
            tilt !== undefined &&
            Number.isFinite(tilt) &&
            tilt <= MAX_EDGE_TILT_RADIANS;

        if (reciprocalFit && !standardFit && edgesSquareToCamera) {
            guidance.push(`Rotate the sheet to ${expectedOrientation}; its detected outline matches the opposite orientation.`);
        } else if (tilt !== undefined && tilt > MAX_EDGE_TILT_RADIANS) {
            guidance.push('Hold the camera square over the sheet to reduce tilt or perspective.');
        } else {
            guidance.push('Align the sheet squarely with the camera.');
        }
    }

    if (!result.gates.steady_ok) {
        guidance.push('Hold the sheet still.');
    }

    if (!result.gates.lighting_ok) {
        guidance.push('Use even lighting and avoid glare or deep shadows.');
    }

    return guidance;
}

const MAX_EDGE = 320;
const MIN_COMPONENT_FRACTION = 0.12;
const MAX_COMPONENT_FRACTION = 0.88;

type Component = {
    area: number;
    bounds: [number, number, number, number];
    corners: [
        [number, number],
        [number, number],
        [number, number],
        [number, number],
    ];
};

function detectPaper(image: ImageData): ViewfinderQuad | null {
    const { width, height, data } = image;
    if (width < 40 || height < 40 || data.length !== width * height * 4) {
        return null;
    }

    const size = width * height;
    const lightness = new Uint8Array(size);
    const borderHistogram = new Uint32Array(256);
    let borderCount = 0;
    const borderWidth = Math.max(2, Math.floor(Math.min(width, height) * 0.03));

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const index = y * width + x;
            const offset = index * 4;
            const value =
                (77 * data[offset] +
                    150 * data[offset + 1] +
                    29 * data[offset + 2]) >>
                8;
            lightness[index] = value;
            if (
                x < borderWidth ||
                x >= width - borderWidth ||
                y < borderWidth ||
                y >= height - borderWidth
            ) {
                borderHistogram[value]++;
                borderCount++;
            }
        }
    }

    let median = 0;
    let count = 0;
    while (median < 255 && count < borderCount / 2) {
        count += borderHistogram[median++];
    }
    const threshold = median + 32;
    if (threshold > 250) {
        return null;
    }

    const visited = new Uint8Array(size);
    const stack = new Int32Array(size);
    let largest: Component | null = null;
    let secondArea = 0;

    for (let start = 0; start < size; start++) {
        if (visited[start] || lightness[start] < threshold) {
            continue;
        }
        visited[start] = 1;
        stack[0] = start;
        let length = 1;
        let area = 0;
        let minX = width;
        let minY = height;
        let maxX = 0;
        let maxY = 0;
        let topLeft = Infinity;
        let topRight = -Infinity;
        let bottomRight = -Infinity;
        let bottomLeft = Infinity;
        const corners: Component['corners'] = [
            [0, 0],
            [0, 0],
            [0, 0],
            [0, 0],
        ];

        while (length > 0) {
            const position = stack[--length];
            const x = position % width;
            const y = (position - x) / width;
            area++;
            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            maxX = Math.max(maxX, x);
            maxY = Math.max(maxY, y);
            if (x + y < topLeft) {
                topLeft = x + y;
                corners[0] = [x, y];
            }
            if (x - y > topRight) {
                topRight = x - y;
                corners[1] = [x, y];
            }
            if (x + y > bottomRight) {
                bottomRight = x + y;
                corners[2] = [x, y];
            }
            if (x - y < bottomLeft) {
                bottomLeft = x - y;
                corners[3] = [x, y];
            }

            if (
                x > 0 &&
                !visited[position - 1] &&
                lightness[position - 1] >= threshold
            ) {
                visited[position - 1] = 1;
                stack[length++] = position - 1;
            }
            if (
                x + 1 < width &&
                !visited[position + 1] &&
                lightness[position + 1] >= threshold
            ) {
                visited[position + 1] = 1;
                stack[length++] = position + 1;
            }
            if (
                y > 0 &&
                !visited[position - width] &&
                lightness[position - width] >= threshold
            ) {
                visited[position - width] = 1;
                stack[length++] = position - width;
            }
            if (
                y + 1 < height &&
                !visited[position + width] &&
                lightness[position + width] >= threshold
            ) {
                visited[position + width] = 1;
                stack[length++] = position + width;
            }
        }

        if (
            area / size < MIN_COMPONENT_FRACTION ||
            maxX - minX < width * 0.25 ||
            maxY - minY < height * 0.25
        ) {
            continue;
        }
        if (!largest || area > largest.area) {
            secondArea = largest?.area ?? 0;
            largest = {
                area,
                bounds: [minX, minY, maxX, maxY],
                corners,
            };
        } else {
            secondArea = Math.max(secondArea, area);
        }
    }

    if (
        !largest ||
        largest.area / size > MAX_COMPONENT_FRACTION ||
        secondArea >= largest.area * 0.45
    ) {
        return null;
    }

    const [minX, minY, maxX, maxY] = largest.bounds;
    const centerX = (minX + maxX) / (2 * width);
    const centerY = (minY + maxY) / (2 * height);
    if (centerX < 0.25 || centerX > 0.75 || centerY < 0.25 || centerY > 0.75) {
        return null;
    }
    return largest.corners.map(([x, y]) => ({
        x: (x + 0.5) / width,
        y: (y + 0.5) / height,
    })) as ViewfinderQuad;
}

function distance(
    a: ViewfinderPoint,
    b: ViewfinderPoint,
    width: number,
    height: number,
): number {
    return Math.hypot((a.x - b.x) * width, (a.y - b.y) * height);
}

function lightingOk(image: ImageData, quad: ViewfinderQuad): boolean {
    const sums = [0, 0, 0, 0];
    let clipped = 0;
    let dark = 0;
    let total = 0;
    const [tl, tr, br, bl] = quad;
    for (let row = 1; row <= 10; row++) {
        const v = row / 11;
        for (let column = 1; column <= 10; column++) {
            const u = column / 11;
            const x = Math.min(
                image.width - 1,
                Math.floor(
                    (tl.x * (1 - u) * (1 - v) +
                        tr.x * u * (1 - v) +
                        br.x * u * v +
                        bl.x * (1 - u) * v) *
                        image.width,
                ),
            );
            const y = Math.min(
                image.height - 1,
                Math.floor(
                    (tl.y * (1 - u) * (1 - v) +
                        tr.y * u * (1 - v) +
                        br.y * u * v +
                        bl.y * (1 - u) * v) *
                        image.height,
                ),
            );
            const offset = (y * image.width + x) * 4;
            const value =
                (77 * image.data[offset] +
                    150 * image.data[offset + 1] +
                    29 * image.data[offset + 2]) >>
                8;
            sums[(row > 5 ? 2 : 0) + (column > 5 ? 1 : 0)] += value;
            clipped += Number(value >= 252);
            dark += Number(value < 85);
            total += value;
        }
    }
    const quadrantMeans = sums.map((sum) => sum / 25);
    return (
        total / 100 >= 130 &&
        total / 100 <= 248 &&
        clipped <= 30 &&
        dark <= 20 &&
        Math.max(...quadrantMeans) - Math.min(...quadrantMeans) <= 52
    );
}

export function analyzeViewfinderImage(
    image: ImageData,
    expectedAspectRatio: number,
    previousQuad: ViewfinderQuad | null = null,
): Pick<ViewfinderResult, 'quad' | 'gates' | 'geometry'> {
    const quad = detectPaper(image);
    const gates: ViewfinderGates = {
        in_bounds: false,
        angle_ok: false,
        steady_ok: false,
        lighting_ok: false,
    };
    if (!quad) {
        return { quad, gates, geometry: null };
    }

    const [tl, tr, br, bl] = quad;
    const width = image.width;
    const height = image.height;
    const horizontal =
        (distance(tl, tr, width, height) + distance(bl, br, width, height)) / 2;
    const vertical =
        (distance(tl, bl, width, height) + distance(tr, br, width, height)) / 2;
    const topTilt = Math.abs(
        Math.atan2((tr.y - tl.y) * height, (tr.x - tl.x) * width),
    );
    const bottomTilt = Math.abs(
        Math.atan2((br.y - bl.y) * height, (br.x - bl.x) * width),
    );
    const leftTilt = Math.abs(
        Math.atan2((bl.x - tl.x) * width, (bl.y - tl.y) * height),
    );
    const rightTilt = Math.abs(
        Math.atan2((br.x - tr.x) * width, (br.y - tr.y) * height),
    );
    const aspect = horizontal / vertical;

    gates.in_bounds = quad.every(
        ({ x, y }) => x >= 0.045 && x <= 0.955 && y >= 0.045 && y <= 0.955,
    );
    const maxEdgeTiltRadians = Math.max(
        topTilt,
        bottomTilt,
        leftTilt,
        rightTilt,
    );
    gates.angle_ok =
        Number.isFinite(expectedAspectRatio) &&
        expectedAspectRatio > 0 &&
        aspect / expectedAspectRatio >= ASPECT_RATIO_TOLERANCE.min &&
        aspect / expectedAspectRatio <= ASPECT_RATIO_TOLERANCE.max &&
        maxEdgeTiltRadians <= MAX_EDGE_TILT_RADIANS;
    gates.steady_ok =
        previousQuad !== null &&
        quad.every(
            (point, index) =>
                distance(point, previousQuad[index], width, height) <=
                Math.min(width, height) * 0.018,
        );
    gates.lighting_ok = lightingOk(image, quad);
    return {
        quad,
        gates,
        geometry: {
            observedAspectRatio: aspect,
            expectedAspectRatio,
            maxEdgeTiltRadians,
        },
    };
}

// The module is also imported by deterministic tests; only a Worker owns the image canvas.
if (typeof self !== 'undefined' && typeof document === 'undefined') {
    let generation = -1;
    let lastQuad: ViewfinderQuad | null = null;
    let lastFrameId = -1;
    let lastCapturedAt = -Infinity;
    let canvas: OffscreenCanvas | null = null;
    const scope = self as unknown as {
        onmessage: ((event: MessageEvent<ViewfinderRequest>) => void) | null;
        postMessage: (result: ViewfinderResult) => void;
    };

    scope.onmessage = ({ data: request }) => {
        if (request.type !== 'analyze') {
            return;
        }
        if (
            request.generation < generation ||
            (request.generation === generation &&
                request.frameId <= lastFrameId)
        ) {
            request.bitmap.close();
            return;
        }
        if (request.generation !== generation) {
            generation = request.generation;
            lastQuad = null;
            lastFrameId = -1;
            lastCapturedAt = -Infinity;
        }
        lastFrameId = request.frameId;
        const { bitmap } = request;
        try {
            const scale = Math.min(
                1,
                MAX_EDGE / Math.max(bitmap.width, bitmap.height),
            );
            const width = Math.max(1, Math.round(bitmap.width * scale));
            const height = Math.max(1, Math.round(bitmap.height * scale));
            if (!canvas) {
                canvas = new OffscreenCanvas(width, height);
            } else if (canvas.width !== width || canvas.height !== height) {
                canvas.width = width;
                canvas.height = height;
            }
            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            });
            if (!context) {
                throw new Error('Canvas 2D context unavailable');
            }
            context.drawImage(bitmap, 0, 0, width, height);
            const result = analyzeViewfinderImage(
                context.getImageData(0, 0, width, height),
                request.expectedAspectRatio,
                request.capturedAt > lastCapturedAt &&
                    request.capturedAt - lastCapturedAt <= 250
                    ? lastQuad
                    : null,
            );
            lastCapturedAt = request.capturedAt;
            lastQuad = result.quad;
            scope.postMessage({
                type: 'analysis',
                generation: request.generation,
                frameId: request.frameId,
                capturedAt: request.capturedAt,
                ...result,
            });
        } catch {
            lastQuad = null;
            scope.postMessage({
                type: 'analysis',
                generation: request.generation,
                frameId: request.frameId,
                capturedAt: request.capturedAt,
                quad: null,
                gates: {
                    in_bounds: false,
                    angle_ok: false,
                    steady_ok: false,
                    lighting_ok: false,
                },
            });
        } finally {
            bitmap.close();
        }
    };
}
