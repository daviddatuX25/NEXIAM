<script lang="ts">
  import { Compass } from 'lucide-svelte';
  import VenueCompassHud from '@/Components/Wayfinding/VenueCompassHud.svelte';

  type Venue = {
    id?: number;
    code: string;
    name: string;
    building_name?: string | null;
    floor_level?: string | null;
    photo_path?: string | null;
    directions_hint?: string | null;
    latitude?: number | null;
    longitude?: number | null;
  };

  let { initialVenue = null }: { initialVenue?: Venue | null } = $props();
  let hudOpen = $state(false);
</script>

<button
  type="button"
  class="fixed bottom-[88px] right-5 z-40 flex h-[48px] w-[48px] items-center justify-center rounded-full border border-border/70 bg-background/85 text-primary shadow-lg backdrop-blur-md transition-all hover:border-primary/50 hover:bg-muted/80 active:scale-95 md:bottom-5 md:right-[80px]"
  aria-label="Open Campus Compass"
  title="Campus Compass & Wayfinder"
  onclick={() => (hudOpen = true)}
>
  <Compass class="size-6 transition-transform duration-300 hover:rotate-12 hover:scale-110" />
</button>

<VenueCompassHud bind:open={hudOpen} venue={initialVenue} onClose={() => (hudOpen = false)} />
