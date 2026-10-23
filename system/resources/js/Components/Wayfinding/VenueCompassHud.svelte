<script lang="ts">
  import 'leaflet/dist/leaflet.css';
  import { onDestroy } from 'svelte';
  import XiamAvatar from '@/Components/kit/Xiam/XiamAvatar.svelte';
  import LocationPrivacyDrawer from '@/Components/Wayfinding/LocationPrivacyDrawer.svelte';
  import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Compass,
    DoorClosed,
    Layers,
    LocateFixed,
    MapPin,
    Maximize2,
    Navigation,
    Search,
    X,
    Zap,
  } from 'lucide-svelte';
  import {
    ContinuousRotationTracker,
    clusterLocalVenues,
    deriveMapViewport,
    deriveTurnInstruction,
    extractDeviceHeading,
    filterGpsJitter,
    forwardAzimuthBearing,
    haversineDistanceMeters,
    relativeNeedleAngle,
  } from '@/lib/wayfinding-engine';

  type Venue = {
    id?: number | null;
    code?: string | null;
    name?: string | null;
    space_kind?: string | null;
    building_name?: string | null;
    floor_level?: string | null;
    photo_path?: string | null;
    photo_url?: string | null;
    directions_hint?: string | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
  };
  type VenueCategory = 'All' | 'Rooms' | 'Offices' | 'Assembly' | 'Service Points';
  type LeafletPolyline = {
    addTo: (map: LeafletMap) => LeafletPolyline;
    setLatLngs: (points: [[number, number], [number, number]]) => LeafletPolyline;
    remove: () => void;
  };
  type LeafletMarker = {
    addTo: (map: LeafletMap) => LeafletMarker;
    on: (event: string, handler: () => void) => void;
    setLatLng: (point: [number, number]) => LeafletMarker;
    remove: () => void;
  };
  type LeafletMap = {
    setView: (center: [number, number], zoom: number) => LeafletMap;
    fitBounds: (bounds: unknown, options?: Record<string, unknown>) => LeafletMap;
    on: (event: 'click', handler: (event: { latlng: { lat: number; lng: number } }) => void) => LeafletMap;
    remove: () => void;
    invalidateSize: () => void;
  };
  type LeafletModule = {
    map: (element: HTMLElement, options?: Record<string, unknown>) => LeafletMap;
    tileLayer: (url: string, options?: Record<string, unknown>) => { addTo: (map: LeafletMap) => unknown };
    divIcon: (options: Record<string, unknown>) => unknown;
    marker: (point: [number, number], options?: Record<string, unknown>) => LeafletMarker;
    polyline: (points: [[number, number], [number, number]], options?: Record<string, unknown>) => LeafletPolyline;
  };

  let { open = $bindable(false), venue = null, roomId = null, onClose }: { open?: boolean; venue?: Venue | null; roomId?: number | null; onClose: () => void } = $props();
  let availableVenues = $state<Venue[]>([]);
  let selectedVenue = $state<Venue | null>(null);
  let venueDismissed = $state(false);
  let currentVenue = $derived(selectedVenue ?? (venueDismissed ? null : venue ?? null));
  let venuesLoaded = $state(false);
  let appliedRoomId = $state<number | null>(null);
  let destinationError = $state('');
  let venuesDrawerOpen = $state(true);
  let venuesRequestStarted = $state(false);
  let previewVenue = $state<Venue | null>(null);
  let venueSearch = $state('');
  let selectedCategory = $state<VenueCategory>('All');
  let mapElement = $state<HTMLElement | null>(null);
  let mapError = $state(false);
  let photoOpen = $state(false);
  let privacyOpen = $state(false);
  let hasConsent = $state(false);
  let permissionPending = $state(false);
  let sensorMessage = $state('Waiting for location');
  let userLat = $state<number | null>(null);
  let userLng = $state<number | null>(null);
  let latestGpsLat = $state<number | null>(null);
  let latestGpsLng = $state<number | null>(null);
  let latestGpsAccuracy = $state<number | null>(null);
  let gpsAccuracy = $state<number | null>(null);
  let deviceHeading = $state<number | null>(null);
  let mapOrientationMode = $state<'heads_up' | 'north_up'>('heads_up');
  let isAdjustingLocation = $state(false);
  let isManualLocationLocked = $state(false);
  let relativeAngle = $state(0);
  let smoothedHeadingDeg = $state<number>(0);
  let smoothedTargetAngle = $state<number>(0);
  let viewportFramedVenueId = $state<string | number | null>(null);
  let directoryViewportFramed = $state(false);
  let watchId: number | null = null;
  let leafletMap: LeafletMap | null = null;
  let routePolyline: LeafletPolyline | null = null;
  let userMarker: LeafletMarker | null = null;
  let leaflet: LeafletModule | null = null;
  const headingRotationTracker = new ContinuousRotationTracker();
  const targetRotationTracker = new ContinuousRotationTracker();
  const renderedVenueKeys = new Set<string>();
  const CONSENT_KEY = 'nexiam_wayfinding_consent_v1';

  let distanceMeters = $derived.by(() => userLat !== null && userLng !== null && currentVenue?.latitude != null && currentVenue?.longitude != null
    ? haversineDistanceMeters(userLat, userLng, Number(currentVenue.latitude), Number(currentVenue.longitude))
    : null);
  let bearing = $derived.by(() => userLat !== null && userLng !== null && currentVenue?.latitude != null && currentVenue?.longitude != null
    ? forwardAzimuthBearing(userLat, userLng, Number(currentVenue.latitude), Number(currentVenue.longitude))
    : null);
  let isNearVenue = $derived(distanceMeters !== null && distanceMeters <= 18 && gpsAccuracy !== null && gpsAccuracy <= 20);
  let isOnTrack = $derived(deviceHeading !== null && Math.abs(((relativeAngle + 180) % 360) - 180) <= 45);
  let turnInstruction = $derived(deriveTurnInstruction(relativeAngle));
  let gpsUnavailable = $derived(userLat === null || distanceMeters === null || sensorMessage.toLowerCase().includes('unavailable'));
  let statusLabel = $derived(!hasConsent ? 'Manual Landmark Mode'
    : gpsUnavailable ? 'GPS Unavailable'
      : gpsAccuracy !== null && gpsAccuracy > 25 ? `Weak GPS (±${Math.round(gpsAccuracy)}m)`
        : isNearVenue ? 'Near Venue'
          : isOnTrack ? 'ON TRACK'
            : 'Orienting');
  let emotion = $derived(isNearVenue ? 'celebrating' : isOnTrack && !gpsUnavailable ? 'hi' : 'idle');
  let dialogue = $derived(!hasConsent
    ? 'Here is what the entrance looks like so you can spot it when you arrive.'
    : isNearVenue
      ? 'GPS places you near the entrance! Match the door photo and sign before checking in.'
      : gpsUnavailable ? 'Location is unavailable. You can still use the entrance photo and landmark directions.'
        : gpsAccuracy !== null && gpsAccuracy > 25 ? 'GPS is too imprecise indoors for reliable direction. Use the entrance photo and landmarks.'
          : deviceHeading === null ? 'Compass heading is unavailable. Use the entrance photo and landmarks.'
            : isOnTrack ? 'You’re facing toward the venue. Follow campus paths and signs.'
              : 'Turn your phone toward the arrow, then follow the paths.');
  let photoUrl = $derived(currentVenue?.photo_url ?? (currentVenue?.photo_path
    ? (currentVenue.photo_path.startsWith('http') || currentVenue.photo_path.startsWith('/') ? currentVenue.photo_path : `/storage/${currentVenue.photo_path}`)
    : null));
  let filteredVenues = $derived(availableVenues.filter((item) => {
    const query = venueSearch.trim().toLowerCase();
    const matchesSearch = !query || [item.code, item.name, item.building_name].some((value) => value?.toLowerCase().includes(query));
    const matchesCategory = selectedCategory === 'All'
      || (selectedCategory === 'Rooms' && (!item.space_kind || item.space_kind === 'room'))
      || (selectedCategory === 'Offices' && item.space_kind === 'office')
      || (selectedCategory === 'Assembly' && item.space_kind === 'assembly_venue')
      || (selectedCategory === 'Service Points' && item.space_kind === 'service_point');
    return matchesSearch && matchesCategory;
  }));
  let previewDistance = $derived.by(() => previewVenue && userLat !== null && userLng !== null && previewVenue.latitude != null && previewVenue.longitude != null
    ? haversineDistanceMeters(userLat, userLng, Number(previewVenue.latitude), Number(previewVenue.longitude))
    : null);

  function mergeVenue(candidate: Venue, venueList: Venue[]): Venue {
    const matched = venueList.find((item) => (candidate.id != null && Number(item.id) === Number(candidate.id)) || (candidate.code != null && item.code === candidate.code));
    return {
      ...matched,
      ...candidate,
      id: candidate.id ?? matched?.id,
      code: candidate.code ?? matched?.code ?? '',
      name: candidate.name ?? matched?.name ?? candidate.code ?? matched?.code ?? '',
      building_name: candidate.building_name ?? matched?.building_name ?? null,
      floor_level: candidate.floor_level ?? matched?.floor_level ?? null,
      directions_hint: candidate.directions_hint ?? matched?.directions_hint ?? null,
      photo_url: candidate.photo_url ?? matched?.photo_url ?? null,
      photo_path: candidate.photo_path ?? matched?.photo_path ?? null,
      latitude: candidate.latitude ?? matched?.latitude ?? null,
      longitude: candidate.longitude ?? matched?.longitude ?? null,
    };
  }

  function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
  }

  function chooseVenue(value: Venue): void {
    selectedVenue = mergeVenue(value, availableVenues);
    venueDismissed = false;
    mapOrientationMode = 'heads_up';
    previewVenue = null;
    photoOpen = false;
  }

  function backToMap(): void {
    selectedVenue = null;
    venueDismissed = true;
    previewVenue = null;
    directoryViewportFramed = false;
  }

  function handleOrientation(event: DeviceOrientationEvent): void {
    const heading = extractDeviceHeading(event);
    if (heading === null) return;
    deviceHeading = heading;
    smoothedHeadingDeg = headingRotationTracker.update(-heading);
    if (bearing !== null) {
      relativeAngle = relativeNeedleAngle(bearing, heading);
      smoothedTargetAngle = targetRotationTracker.update(relativeAngle);
    }
  }

  function startLocation(): void {
    if (!navigator.geolocation) {
      sensorMessage = 'Location unavailable on this device.';
      return;
    }
    watchId = navigator.geolocation.watchPosition((position) => {
      latestGpsLat = position.coords.latitude;
      latestGpsLng = position.coords.longitude;
      latestGpsAccuracy = position.coords.accuracy;
      const filterResult = filterGpsJitter(
        userLat,
        userLng,
        position.coords.latitude,
        position.coords.longitude,
        position.coords.accuracy,
      );
      gpsAccuracy = position.coords.accuracy;
      sensorMessage = 'Location active';
      if (isManualLocationLocked || !filterResult.changed) return;
      userLat = filterResult.lat;
      userLng = filterResult.lng;
    }, () => {
      latestGpsLat = null;
      latestGpsLng = null;
      latestGpsAccuracy = null;
      if (isManualLocationLocked) return;
      userLat = null;
      userLng = null;
      gpsAccuracy = null;
      sensorMessage = 'Location unavailable. You can still use the venue details.';
    }, { enableHighAccuracy: true });
  }

  function toggleMapOrientation(): void {
    mapOrientationMode = mapOrientationMode === 'heads_up' ? 'north_up' : 'heads_up';
  }

  function resetManualLocation(): void {
    isManualLocationLocked = false;
    isAdjustingLocation = false;
    userLat = latestGpsLat;
    userLng = latestGpsLng;
    gpsAccuracy = latestGpsAccuracy;
    sensorMessage = latestGpsLat === null ? 'Waiting for live location' : 'Location active';
  }

  function handleMapClick(event: { latlng: { lat: number; lng: number } }): void {
    if (!isAdjustingLocation) return;
    userLat = event.latlng.lat;
    userLng = event.latlng.lng;
    gpsAccuracy = null;
    isManualLocationLocked = true;
    isAdjustingLocation = false;
    sensorMessage = 'Manual location active';
  }


  function updateMapOrientation(): void {
    if (!mapElement) return;
    mapElement.style.setProperty('--device-heading', `${deviceHeading ?? 0}deg`);
    mapElement.style.setProperty('--device-heading-visibility', deviceHeading === null ? 'hidden' : 'visible');
  }

  async function enableCompass(): Promise<void> {
    if (typeof DeviceOrientationEvent === 'undefined') {
      permissionPending = false;
      sensorMessage = 'Compass heading unavailable.';
      return;
    }
    const orientation = DeviceOrientationEvent as typeof DeviceOrientationEvent & { requestPermission?: () => Promise<PermissionState> };
    if (typeof orientation.requestPermission === 'function') {
      try {
        permissionPending = await orientation.requestPermission() !== 'granted';
      } catch {
        permissionPending = true;
      }
    } else {
      permissionPending = false;
    }
  }

  function acceptConsent(): void {
    try {
      localStorage.setItem(CONSENT_KEY, 'granted');
    } catch {
      // Consent remains available for this visit.
    }
    hasConsent = true;
    privacyOpen = false;
  }

  function declineConsent(): void {
    privacyOpen = false;
    hasConsent = false;
    sensorMessage = 'Location unavailable. You can still use the venue details.';
  }

  function close(): void {
    open = false;
    onClose();
  }

  $effect(() => {

    if (!open || venuesRequestStarted) return;
    venuesRequestStarted = true;
    void fetch('/api/wayfinding/venues')
      .then((response) => response.json())
      .then((venues: unknown) => {
        const list = Array.isArray(venues) ? venues : (venues && typeof venues === 'object' && 'data' in venues && Array.isArray(venues.data) ? venues.data : []);
        availableVenues = list as Venue[];
        if (venue) selectedVenue = mergeVenue(venue, availableVenues);
        venuesLoaded = true;
      })
      .catch(() => { availableVenues = []; venuesLoaded = true; });
  });

  $effect(() => {
    if (!open) {
      appliedRoomId = null;
      destinationError = '';
      return;
    }
    if (roomId === null || !venuesLoaded || appliedRoomId === roomId) return;
    appliedRoomId = roomId;
    const requested = availableVenues.find((item) => Number(item.id) === roomId);
    if (requested) {
      chooseVenue(requested);
      venuesDrawerOpen = false;
    } else {
      selectedVenue = null;
      destinationError = 'This office is not available in the current venue directory. Use the recorded office details or contact the office.';
    }
  });

  function updateMapViewport(recenter = false): void {
    const map = leafletMap;
    const leafletInstance = leaflet;
    if (!map || !leafletInstance) return;

    if (userLat !== null && userLng !== null) {
      if (userMarker) {
        userMarker.setLatLng([userLat, userLng]);
      } else {
        userMarker = leafletInstance.marker([userLat, userLng], {
          title: 'Your location',
          keyboard: false,
          icon: leafletInstance.divIcon({
            className: 'campus-user-marker',
            html: '<span aria-hidden="true"></span>',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
          }),
        }).addTo(map);
      }
    } else if (userMarker) {
      userMarker.remove();
      userMarker = null;
    }

    for (const item of availableVenues) {
      if (item.latitude == null || item.longitude == null) continue;
      const key = String(item.id ?? item.code ?? `${item.latitude},${item.longitude}`);
      if (renderedVenueKeys.has(key)) continue;
      const code = item.code ?? '';
      const marker = leafletInstance.marker([Number(item.latitude), Number(item.longitude)], {
        icon: leafletInstance.divIcon({
          className: 'campus-venue-marker',
          html: `<span>${escapeHtml(code)}</span>`,
          iconSize: [92, 34],
          iconAnchor: [46, 17],
        }),
      }).addTo(map);
      marker.on('click', () => { previewVenue = item; });
      renderedVenueKeys.add(key);
    }

    if (currentVenue === null) {
      routePolyline?.remove();
      routePolyline = null;
      if (recenter || (!directoryViewportFramed && (venuesLoaded || userLat !== null))) {
        const localVenues = clusterLocalVenues(availableVenues, userLat !== null && userLng !== null
          ? { latitude: userLat, longitude: userLng }
          : null);
        if (localVenues.length === 1) {
          map.setView([Number(localVenues[0].latitude), Number(localVenues[0].longitude)], 19);
        } else if (localVenues.length > 1) {
          map.fitBounds(localVenues.map((item) => [Number(item.latitude), Number(item.longitude)]), { padding: [36, 36], maxZoom: 19 });
        } else if (userLat !== null && userLng !== null) {
          map.setView([userLat, userLng], 18);
        } else {
          map.setView([0, 0], 2);
        }
        directoryViewportFramed = true;
      }
      updateMapOrientation();
      return;
    }

    const venueLat = currentVenue.latitude == null ? null : Number(currentVenue.latitude);
    const venueLng = currentVenue.longitude == null ? null : Number(currentVenue.longitude);
    if (userLat !== null && userLng !== null && venueLat !== null && venueLng !== null) {
      const points: [[number, number], [number, number]] = [[userLat, userLng], [venueLat, venueLng]];
      if (routePolyline) {
        routePolyline.setLatLngs(points);
      } else {
        routePolyline = leafletInstance.polyline(points, { dashArray: '6, 8', color: '#2563eb', weight: 4 }).addTo(map);
      }
    } else {
      routePolyline?.remove();
      routePolyline = null;
    }

    const venueId = currentVenue.id ?? currentVenue.code ?? currentVenue.name ?? 'unknown';
    if (recenter || viewportFramedVenueId !== venueId) {
      const viewport = deriveMapViewport(userLat, userLng, venueLat, venueLng, distanceMeters);
      if (viewport.mode === 'doorstep' && viewport.center) {
        map.setView(viewport.center, 19);
      } else if (viewport.mode === 'navigating' && viewport.bounds) {
        map.fitBounds(viewport.bounds, { padding: [36, 36], maxZoom: 19 });
      } else if (viewport.mode === 'target_only' && viewport.center) {
        map.setView(viewport.center, 18);
      } else if (viewport.center) {
        map.setView(viewport.center, viewport.zoom);
      }
      viewportFramedVenueId = venueId;
    }
    updateMapOrientation();
  }
  $effect(() => {
    deviceHeading;
    updateMapOrientation();
  });

  $effect(() => {
    const element = mapElement;
    if (!open || !element || typeof window === 'undefined') return;
    let disposed = false;
    let localMap: LeafletMap | null = null;
    void (async () => {
      try {
        const module = await import('leaflet');
        if (disposed) return;
        const leafletInstance = (module.default ?? module) as unknown as LeafletModule;
        leaflet = leafletInstance;
        localMap = leafletInstance.map(element, { scrollWheelZoom: false });
        localMap.on('click', handleMapClick);
        leafletMap = localMap;
        leafletInstance.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxZoom: 19,
        }).addTo(localMap);
        updateMapViewport();
        window.setTimeout(() => { if (!disposed) localMap?.invalidateSize(); }, 0);
        mapError = false;
      } catch {
        mapError = true;
      }
    })();
    return () => {
      disposed = true;
      localMap?.remove();
      if (leafletMap === localMap) {
        leafletMap = null;
        leaflet = null;
        renderedVenueKeys.clear();
      }
    };
  });

  $effect(() => {
    if (!open) return;
    currentVenue;
    availableVenues;
    userLat;
    userLng;
    distanceMeters;
    updateMapViewport();
  });

  $effect(() => {
    if (!open) {
      viewportFramedVenueId = null;
      directoryViewportFramed = false;
      return;
    }
    if (!currentVenue) return;
    hasConsent = typeof localStorage !== 'undefined' && localStorage.getItem(CONSENT_KEY) === 'granted';
    if (!hasConsent) {
      privacyOpen = true;
      return;
    }
    startLocation();
    if (typeof DeviceOrientationEvent === 'undefined') {
      sensorMessage = 'Compass heading unavailable.';
    } else {
      const orientation = DeviceOrientationEvent as typeof DeviceOrientationEvent & { requestPermission?: () => Promise<PermissionState> };
      if (typeof orientation.requestPermission === 'function') permissionPending = true;
    }
    const orientationEvent = 'ondeviceorientationabsolute' in window ? 'deviceorientationabsolute' : 'deviceorientation';
    window.addEventListener(orientationEvent, handleOrientation as EventListener);
    return () => {
      if (watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(watchId);
      watchId = null;
      window.removeEventListener(orientationEvent, handleOrientation as EventListener);
    };
  });

  onDestroy(() => {
    if (watchId !== null && typeof navigator !== 'undefined' && navigator.geolocation) navigator.geolocation.clearWatch(watchId);
    leafletMap?.remove();
    leafletMap = null;
    routePolyline = null;
    userMarker = null;
    renderedVenueKeys.clear();
  });
</script>

{#if open}
  <div class="fixed inset-0 z-[80] flex items-end justify-center bg-black/65 sm:items-center sm:p-6" role="presentation" onclick={(event) => { if (event.target === event.currentTarget) close(); }}>
    <div class="relative flex max-h-[96dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl bg-background text-foreground shadow-2xl sm:max-h-[min(680px,calc(100dvh-3rem))] sm:max-w-2xl sm:rounded-3xl" role="dialog" aria-modal="true" aria-label={currentVenue?.name ?? 'Campus Compass'}>
      {#if currentVenue === null}
        <header class="z-20 flex shrink-0 items-center justify-between gap-3 border-b bg-background/95 px-4 py-3">
          <div class="min-w-0"><p class="truncate text-sm font-semibold">Campus Compass</p><p class="truncate text-xs text-muted-foreground">Select a Destination</p></div>
          <div class="flex shrink-0 items-center gap-2">
          <button class="inline-flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm font-medium hover:bg-muted" aria-label={`Venues (${availableVenues.length})`} aria-expanded={venuesDrawerOpen} onclick={() => (venuesDrawerOpen = !venuesDrawerOpen)}><Layers class="size-4" />Venues ({availableVenues.length})</button>
            <button class="rounded-full p-2 hover:bg-muted" aria-label="Close wayfinding" onclick={close}><X class="size-5" /></button>
          </div>
        </header>
      {:else}
        <header class="z-20 flex shrink-0 items-center gap-3 border-b bg-background/95 px-4 py-3">
          <button class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-2 text-sm font-medium hover:bg-muted" aria-label="Back to Map" onclick={backToMap}><ArrowLeft class="size-4" />Back to Map</button>
          <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{currentVenue.name}</p><p class="truncate text-xs text-muted-foreground">{[currentVenue.building_name, currentVenue.floor_level].filter(Boolean).join(' · ') || currentVenue.code}</p></div>
          <button class="shrink-0 rounded-full p-2 hover:bg-muted" aria-label="Close wayfinding" onclick={close}><X class="size-5" /></button>
        </header>
      {/if}
      {#if destinationError}<p class="border-b px-4 py-3 text-sm text-muted-foreground" role="status">{destinationError}</p>{/if}
      <div class="relative h-[240px] shrink-0 overflow-hidden isolate bg-slate-100 text-slate-900 sm:h-[220px] sm:max-h-[35dvh]">
        <div bind:this={mapElement} class="absolute inset-0 z-0" role="application" aria-label="Interactive campus map"></div>
        <div class="pointer-events-none absolute inset-x-3 top-3 z-[500] flex items-start justify-between gap-2">
          {#if currentVenue !== null}
            <div class="pointer-events-auto flex max-w-[65%] flex-col items-start gap-2">
              {#if isAdjustingLocation}<span class="rounded-lg border border-slate-300 bg-white/95 px-3 py-2 text-xs font-medium text-slate-900 shadow-md">Tap the map where you are.</span>{/if}
              {#if isManualLocationLocked}<span class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-300 bg-white/95 px-2.5 py-1.5 text-xs text-slate-900 shadow-md">Manual Location Active · <button class="font-semibold text-blue-600 underline underline-offset-2 hover:text-blue-800" onclick={resetManualLocation}>Reset to GPS</button></span>{/if}
            </div>
          {/if}
          <button class="pointer-events-auto ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 bg-white/95 px-3 py-2 text-xs font-semibold text-slate-900 shadow-md hover:bg-slate-50" aria-label={`Toggle map orientation: ${mapOrientationMode === 'heads_up' ? 'Heads Up' : 'North Up'}`} title={mapOrientationMode === 'heads_up' ? 'Heads Up' : 'North Up'} onclick={toggleMapOrientation}><Compass class="size-4 text-slate-800" />{mapOrientationMode === 'heads_up' ? 'Heads Up' : 'North Up'}</button>
        </div>
        {#if currentVenue !== null}
          <button class="absolute bottom-10 right-2 z-[500] inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white/95 px-2 py-1 text-xs font-semibold text-slate-900 shadow-md hover:bg-slate-50" aria-label="Recenter map" onclick={() => updateMapViewport(true)}><LocateFixed class="size-3.5" />Recenter</button>
          <button class="absolute bottom-2 right-2 z-[500] inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white/95 px-2 py-1 text-xs font-semibold text-slate-900 shadow-md hover:bg-slate-50" aria-label="Adjust my location" onclick={() => (isAdjustingLocation = true)}><MapPin class="size-3.5 text-slate-800" />Adjust My Location</button>
        {/if}
        {#if mapError}<div class="absolute inset-x-4 top-14 z-[510] rounded-xl border border-slate-300 bg-white/95 p-3 text-sm text-slate-800 shadow-md">The campus map is unavailable in this environment. Choose a venue from the directory.</div>{/if}
      </div>
      {#if currentVenue === null}
        {#if venuesDrawerOpen}
          <section class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4" aria-label="Venue directory">
            {#if previewVenue}
              <div class="rounded-2xl border bg-card p-4">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-semibold">{previewVenue.name} · {previewVenue.code}</p><p class="mt-1 text-sm text-muted-foreground">{[previewVenue.building_name, previewVenue.floor_level].filter(Boolean).join(' · ') || 'Building details unavailable'}</p>{#if previewDistance !== null}<p class="mt-1 text-xs text-muted-foreground">{Math.round(previewDistance)} m away</p>{/if}</div><button class="rounded-full p-1.5 hover:bg-muted" aria-label="Close venue preview" onclick={() => (previewVenue = null)}><X class="size-4" /></button></div>
                <button class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground" onclick={() => chooseVenue(previewVenue!)}><MapPin class="size-4" />Start Wayfinding<ArrowRight class="size-4" /></button>
              </div>
            {/if}
            <div class="space-y-3">
              <div><h2 class="font-semibold">Venues</h2><p class="text-xs text-muted-foreground">Choose a destination on campus.</p></div>
              <label class="relative block"><Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input bind:value={venueSearch} type="search" placeholder="Search venues" aria-label="Search venues by code, name, or building" class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm" /></label>
              <div class="flex flex-wrap gap-1.5" aria-label="Venue categories">
                {#each ['All', 'Rooms', 'Offices', 'Assembly', 'Service Points'] as category}
                  <button class={`rounded-full border px-2.5 py-1 text-xs ${selectedCategory === category ? 'border-primary bg-primary text-primary-foreground' : 'bg-muted'}`} aria-pressed={selectedCategory === category} onclick={() => (selectedCategory = category as VenueCategory)}>{category}</button>
                {/each}
              </div>
              <ul class="space-y-1">
                {#each filteredVenues as item (item.id ?? item.code)}
                  <li><button class="w-full rounded-lg px-3 py-2.5 text-left transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" onclick={() => chooseVenue(item)}><span class="block text-sm font-medium">{item.name} · {item.code}</span><span class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"><Building2 class="size-3.5" />{[item.building_name, item.floor_level].filter(Boolean).join(' · ') || 'Building details unavailable'}</span></button></li>
                {:else}
                  <li class="p-3 text-sm text-muted-foreground">{venuesLoaded ? 'No destinations match your search.' : 'Loading destinations…'}</li>
                {/each}
              </ul>
            </div>
          </section>
        {:else}
          <div class="p-4 text-sm text-muted-foreground">Venue directory hidden.</div>
        {/if}
      {:else}
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
          <section class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" aria-label="Live location and compass">
            <div class="rounded-2xl border bg-card p-4">
              <div class="flex items-center justify-between gap-2"><p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Distance to destination</p><span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium">{#if statusLabel === 'ON TRACK'}<Zap class="size-3" />{:else if statusLabel.includes('GPS') || statusLabel.includes('Weak')}<MapPin class="size-3" />{:else}<Compass class="size-3" />{/if}{statusLabel}</span></div>
              <p class="mt-1 text-3xl font-bold tabular-nums">{distanceMeters === null ? (gpsUnavailable ? (hasConsent ? 'Distance unavailable' : 'Waiting for GPS') : 'Waiting for GPS') : `${Math.round(distanceMeters)} m`}</p>
              <p class="mt-1 text-sm font-semibold">{distanceMeters === null || bearing === null ? '—' : Math.abs(((relativeAngle + 180) % 360) - 180) > 90 ? 'BEHIND' : 'AHEAD'}</p>
              <p class="mt-2 text-xs text-muted-foreground">{gpsAccuracy === null ? (gpsUnavailable ? 'GPS accuracy unavailable' : 'Waiting for GPS accuracy') : `GPS accuracy ±${Math.round(gpsAccuracy)}m`}</p>
              {#if permissionPending}<button class="mt-3 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" onclick={enableCompass}>Enable Compass Sensor</button>{/if}
            </div>
            <div class="flex flex-col items-center justify-center space-y-3 rounded-2xl border bg-card p-4 text-center">
              <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1.5 text-sm font-semibold text-primary" aria-live="polite"><Navigation class="size-4" />{turnInstruction.text} ({Math.round(relativeAngle)}°)</div>
              <div class="flex items-center justify-center gap-1.5 text-xs font-semibold text-muted-foreground"><Compass class="size-3.5 text-primary" /><span>Facing: {deviceHeading === null ? '—' : `${Math.round(deviceHeading)}° ${deviceHeading >= 337.5 || deviceHeading < 22.5 ? 'N' : deviceHeading < 67.5 ? 'NE' : deviceHeading < 112.5 ? 'E' : deviceHeading < 157.5 ? 'SE' : deviceHeading < 202.5 ? 'S' : deviceHeading < 247.5 ? 'SW' : deviceHeading < 292.5 ? 'W' : 'NW'}`}</span></div>
              <div class="relative grid size-48 place-items-center rounded-full border-4 border-muted/80 bg-card shadow-lg sm:size-36">
                <div class="absolute -top-1.5 z-10 flex flex-col items-center"><div class="h-2 w-1 rounded-full bg-primary"></div></div>
                <div class="absolute inset-1.5 grid place-items-center rounded-full border border-border/80 transition-transform duration-300 ease-out" style={`transform: rotate(${smoothedHeadingDeg}deg)`}>
                  <span class="absolute top-1 text-xs font-black text-red-600">N</span>
                  <span class="absolute right-2 text-[10px] font-bold text-muted-foreground">E</span>
                  <span class="absolute bottom-1 text-[10px] font-bold text-muted-foreground">S</span>
                  <span class="absolute left-2 text-[10px] font-bold text-muted-foreground">W</span>
                  <div class="size-24 rounded-full border border-border/60 bg-muted/10 sm:size-20"></div>
                </div>
                <div class="pointer-events-none absolute inset-0 z-10 grid place-items-center transition-transform duration-300 ease-out" style={`transform: rotate(${smoothedTargetAngle}deg); transform-origin: 50% 50%;`} aria-label={`Compass direction ${Math.round(relativeAngle)} degrees`}>
                  <svg viewBox="0 0 100 100" class="size-full overflow-visible">
                    <!-- Subtle pivot ring -->
                    <circle cx="50" cy="50" r="16" class="fill-primary/10 stroke-primary/30" stroke-width="1.5" />
                    <!-- Tail (South / away from target) -->
                    <polygon points="50,82 46,50 54,50" class="fill-muted-foreground/40" />
                    <!-- Left facet of forward target needle -->
                    <polygon points="50,14 44,50 50,44" class="fill-primary" />
                    <!-- Right facet of forward target needle (light accent) -->
                    <polygon points="50,14 50,44 56,50" class="fill-primary/80" />
                    <!-- Center cap pin -->
                    <circle cx="50" cy="50" r="5" class="fill-background stroke-primary" stroke-width="2" />
                    <circle cx="50" cy="50" r="2" class="fill-primary" />
                  </svg>
                </div>
              </div>
              <div class="flex items-center justify-center gap-4 text-[11px] text-muted-foreground"><div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-red-600"></span><span>True North</span></div><div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-primary"></span><span>Destination</span></div></div>
            </div>
          </section>
          <section class="flex items-center gap-3 rounded-2xl border bg-muted/30 p-4" aria-label="Kuya Xiam guidance"><XiamAvatar size={64} heldEmotion={emotion} label="Kuya Xiam" /><div class="min-w-0"><p class="text-sm font-medium">{dialogue}</p>{#if currentVenue.directions_hint}<p class="mt-1 text-xs text-muted-foreground">{currentVenue.directions_hint}</p>{:else}<p class="mt-1 text-xs text-muted-foreground">Use the landmark directions to find your destination.</p>{/if}</div></section>
          <section class="overflow-hidden rounded-2xl border bg-card" aria-label="Entrance door photo">
            <div class="flex items-center gap-3 p-3"><div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted">{#if photoUrl}<img src={photoUrl} alt="Entrance door" class="h-full w-full object-cover object-top" />{:else}<DoorClosed class="size-7 text-muted-foreground" />{/if}</div><div class="min-w-0 flex-1"><p class="text-sm font-semibold">Entrance door</p><p class="text-xs text-muted-foreground">{photoUrl ? 'Compare the entrance with the building sign.' : 'No entrance photo is available.'}</p></div>{#if photoUrl}<button class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted" aria-label="Expand door photo" onclick={() => (photoOpen = true)}><Maximize2 class="size-4" />Expand Photo</button>{/if}</div>
          </section>
        </div>
      {/if}
    </div>
  </div>
  {#if currentVenue !== null}
    <LocationPrivacyDrawer bind:open={privacyOpen} onAccept={acceptConsent} onDecline={declineConsent} targetVenueName={currentVenue.name ?? currentVenue.code ?? 'destination'} />
    {#if photoOpen && photoUrl}<div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-3" role="presentation" onclick={(event) => { if (event.target === event.currentTarget) photoOpen = false; }}><div role="dialog" aria-modal="true" aria-label="Expanded entrance photo" tabindex="-1" class="relative max-h-full max-w-full"><button class="absolute right-4 top-4 rounded-full bg-black/70 p-2 text-white" aria-label="Close photo" onclick={() => (photoOpen = false)}><X class="size-6" /></button><img src={photoUrl} alt={`Full door photo for ${currentVenue.name}`} class="max-h-[94dvh] max-w-full object-contain object-top" /></div></div>{/if}
  {/if}
{/if}

<style>
  :global(.leaflet-container) { color: #0f172a !important; background-color: #e2e8f0 !important; }
  :global(.leaflet-control-zoom a) { color: #0f172a !important; background-color: #ffffff !important; border-color: #cbd5e1 !important; }
  :global(.leaflet-control-attribution) { background-color: rgb(255 255 255 / 0.85) !important; color: #475569 !important; }
  :global(.leaflet-control-attribution a) { color: #2563eb !important; }
  :global(.campus-venue-marker) { background: transparent; border: 0; }
  :global(.campus-venue-marker span) { display: block; width: max-content; max-width: 92px; overflow: hidden; border: 1px solid rgb(255 255 255 / 0.85); border-radius: 9999px; background: hsl(var(--primary)); padding: 5px 9px; color: hsl(var(--primary-foreground)); font-family: inherit; font-size: 11px; font-weight: 600; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; box-shadow: 0 2px 8px rgb(0 0 0 / 0.3); }
  :global(.campus-user-marker) { display: grid; place-items: center; border: 0; background: transparent; }
  :global(.campus-user-marker span) { position: relative; display: block; width: 14px; height: 14px; border: 3px solid white; border-radius: 9999px; background: #2563eb; box-shadow: 0 0 0 5px rgb(37 99 235 / 0.2), 0 1px 5px rgb(0 0 0 / 0.35); transform: rotate(var(--device-heading, 0deg)); transform-origin: center; }
  :global(.campus-user-marker span::before) { position: absolute; bottom: 5px; left: 50%; width: 0; height: 0; border-right: 7px solid transparent; border-bottom: 13px solid rgb(37 99 235 / 0.55); border-left: 7px solid transparent; content: ''; transform: translateX(-50%); visibility: var(--device-heading-visibility, hidden); }
</style>
