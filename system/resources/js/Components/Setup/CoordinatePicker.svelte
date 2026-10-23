<script>
    import 'leaflet/dist/leaflet.css';
    import markerIcon from 'leaflet/dist/images/marker-icon.png';
    import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
    import markerShadow from 'leaflet/dist/images/marker-shadow.png';

    let {
        latitude = $bindable(null),
        longitude = $bindable(null),
        height = '280px',
        readonly = false,
    } = $props();

    let mapElement = $state(null);
    let mapReady = $state(false);
    let mapInstance = null;
    let markerInstance = null;
    let leaflet = null;
    let venueIcon = null;

    function coordinateFromInput(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }

        const parsed = Number(value);
        return Number.isFinite(parsed) ? Number(parsed.toFixed(7)) : null;
    }

    function updateCoordinates(point) {
        latitude = Number(point.lat.toFixed(7));
        longitude = Number(point.lng.toFixed(7));
    }

    function hasCoordinates(lat, lng) {
        return (
            lat !== null &&
            lat !== undefined &&
            lng !== null &&
            lng !== undefined &&
            Number.isFinite(Number(lat)) &&
            Number.isFinite(Number(lng))
        );
    }

    $effect(() => {
        const element = mapElement;
        if (!element || typeof window === 'undefined') {
            return;
        }

        let disposed = false;
        void import('leaflet').then((module) => {
            if (disposed) {
                return;
            }

            leaflet = module.default ?? module;

            delete leaflet.Icon.Default.prototype._getIconUrl;
            leaflet.Icon.Default.mergeOptions({
                iconUrl: markerIcon,
                iconRetinaUrl: markerIcon2x,
                shadowUrl: markerShadow,
            });

            venueIcon = leaflet.icon({
                iconUrl: markerIcon,
                iconRetinaUrl: markerIcon2x,
                shadowUrl: markerShadow,
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            });

            const initialCoords = hasCoordinates(latitude, longitude);
            const initialPoint = initialCoords
                ? [Number(latitude), Number(longitude)]
                : [0, 0];
            const initialZoom = initialCoords ? 18 : 2;

            mapInstance = leaflet
                .map(element, { scrollWheelZoom: false })
                .setView(initialPoint, initialZoom);
            leaflet
                .tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                })
                .addTo(mapInstance);
            if (!readonly) {
                mapInstance.on('click', (event) =>
                    updateCoordinates(event.latlng),
                );
            }
            mapReady = true;
        });

        return () => {
            disposed = true;
            mapInstance?.remove();
            mapInstance = null;
            markerInstance = null;
            leaflet = null;
            venueIcon = null;
            mapReady = false;
        };
    });

    $effect(() => {
        const nextLatitude = latitude;
        const nextLongitude = longitude;
        if (!mapReady || !mapInstance || !leaflet) {
            return;
        }

        if (!hasCoordinates(nextLatitude, nextLongitude)) {
            markerInstance?.remove();
            markerInstance = null;
            return;
        }

        const point = [Number(nextLatitude), Number(nextLongitude)];
        const iconToUse =
            venueIcon ??
            leaflet.icon({
                iconUrl: markerIcon,
                iconRetinaUrl: markerIcon2x,
                shadowUrl: markerShadow,
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            });

        if (!markerInstance) {
            markerInstance = leaflet
                .marker(point, {
                    icon: iconToUse,
                    draggable: !readonly,
                })
                .addTo(mapInstance);
            markerInstance.on('dragend', () =>
                updateCoordinates(markerInstance.getLatLng()),
            );
        } else {
            markerInstance.setLatLng(point);
            if (iconToUse) {
                markerInstance.setIcon(iconToUse);
            }
        }

        if (mapInstance.getZoom() <= 4) {
            mapInstance.setView(point, 18);
        } else {
            mapInstance.panTo(point);
        }
    });
</script>

<div class="space-y-3">
    <div
        bind:this={mapElement}
        class="border-border bg-muted z-0 w-full overflow-hidden rounded-lg border"
        style:height
        role="application"
        aria-label="Map for selecting venue entrance coordinates"
    ></div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="space-y-1.5">
            <label for="venue-latitude" class="text-sm font-medium"
                >Latitude</label
            >
            <input
                id="venue-latitude"
                type="number"
                step="0.0000001"
                min="-90"
                max="90"
                value={latitude ?? ''}
                oninput={(event) =>
                    (latitude = coordinateFromInput(event.currentTarget.value))}
                disabled={readonly}
                aria-label="Latitude in decimal degrees"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>
        <div class="space-y-1.5">
            <label for="venue-longitude" class="text-sm font-medium"
                >Longitude</label
            >
            <input
                id="venue-longitude"
                type="number"
                step="0.0000001"
                min="-180"
                max="180"
                value={longitude ?? ''}
                oninput={(event) =>
                    (longitude = coordinateFromInput(
                        event.currentTarget.value,
                    ))}
                disabled={readonly}
                aria-label="Longitude in decimal degrees"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>
    </div>
</div>
