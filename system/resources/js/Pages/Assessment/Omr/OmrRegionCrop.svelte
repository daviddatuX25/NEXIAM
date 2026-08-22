<script>
  import { onMount, onDestroy } from 'svelte';

  let {
    imageUrl = null,
    region = null,
    options = [],
    detectedOption = null,
    correctOption = null,
    state: itemState = null,
    alt = '',
    title = '',
    width = 170,
  } = $props();

  let src = $state(null);
  let failed = $state(false);
  let container;
  let observer = null;

  // Padded normalized box: generous left pad so the row label is visible,
  // small pads on the other sides. Clamped to the page.
  const box = $derived.by(() => {
    if (!region || Number(region.w) <= 0 || Number(region.h) <= 0) return null;
    const padLeft = region.w * 0.22;
    const padRight = region.w * 0.05;
    const padTop = region.h * 0.25;
    const padBottom = region.h * 0.2;
    const x = Math.max(0, region.x - padLeft);
    const y = Math.max(0, region.y - padTop);
    const w = Math.min(1 - x, region.w + padLeft + padRight);
    const h = Math.min(1 - y, region.h + padTop + padBottom);
    return { x, y, w, h };
  });

  const stateClass = $derived(
    itemState === 'marked' && detectedOption && detectedOption === correctOption
      ? 'correct'
      : itemState === 'marked' && detectedOption
        ? 'incorrect'
        : itemState === 'ambiguous' || itemState === 'unreadable'
          ? 'ambiguous'
          : null,
  );

  function markerStyle(option) {
    if (!box || !option?.region || Number(option.region.w) <= 0 || Number(option.region.h) <= 0) return '';
    return `left:${((option.region.x - box.x) / box.w) * 100}%;top:${((option.region.y - box.y) / box.h) * 100}%;width:${(option.region.w / box.w) * 100}%;height:${(option.region.h / box.h) * 100}%;`;
  }

  function markerClass(option) {
    if (itemState === 'ambiguous' || itemState === 'unreadable') return 'ambiguous';
    if (option.code === detectedOption) return option.code === correctOption ? 'correct' : 'incorrect';
    return 'plain';
  }

  function generate() {
    if (src || failed || !imageUrl || !box) return;
    const img = new Image();
    img.decoding = 'async';
    img.onload = () => {
      try {
        const naturalWidth = img.naturalWidth || 1;
        const naturalHeight = img.naturalHeight || 1;
        const outWidth = width;
        const outHeight = Math.max(16, Math.round((box.h / box.w) * outWidth));
        const canvas = document.createElement('canvas');
        canvas.width = outWidth;
        canvas.height = outHeight;
        const context = canvas.getContext('2d');
        if (!context) {
          failed = true;
          return;
        }
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(
          img,
          box.x * naturalWidth,
          box.y * naturalHeight,
          box.w * naturalWidth,
          box.h * naturalHeight,
          0,
          0,
          outWidth,
          outHeight,
        );
        src = canvas.toDataURL('image/jpeg', 0.85);
      } catch {
        failed = true;
      }
    };
    img.onerror = () => {
      failed = true;
    };
    img.src = imageUrl;
  }

  onMount(() => {
    if (!('IntersectionObserver' in window)) {
      generate();
      return;
    }
    observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          observer?.disconnect();
          generate();
          break;
        }
      }
    }, { rootMargin: '160px' });
    observer.observe(container);
  });

  onDestroy(() => observer?.disconnect());
</script>

<div
  class="region-crop"
  class:correct={stateClass === 'correct'}
  class:incorrect={stateClass === 'incorrect'}
  class:ambiguous={stateClass === 'ambiguous'}
  class:failed={failed}
  bind:this={container}
  role="img"
  aria-label={alt}
  title={title || alt}
>
  {#if src}
    <img class="crop-img" src={src} alt={alt} draggable="false" />
    {#if options.length > 0 && itemState !== 'blank'}
      {#each options as option (option.code)}
        <i class="option-marker {markerClass(option)}" style={markerStyle(option)} title={`Option ${option.code}`}>{option.code}</i>
      {/each}
    {/if}
  {:else if failed}
    <span class="crop-note">Crop unavailable</span>
  {:else}
    <span class="crop-note">Loading crop…</span>
  {/if}
</div>

<style>
  .region-crop {
    position: relative;
    width: 100%;
    min-height: 22px;
    overflow: hidden;
    border: 1px solid var(--omr-border, #d8dee6);
    border-radius: 5px;
    background: var(--omr-stage-deep, #16211a);
    color: var(--omr-muted, #8a9a90);
    display: grid;
    place-items: center;
    font: 10px var(--font-mono, monospace);
  }
  .region-crop.correct {
    border-color: #2f8f62;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, #2f8f62 55%, transparent);
  }
  .region-crop.incorrect {
    border-color: #b94a48;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, #b94a48 55%, transparent);
  }
  .region-crop.ambiguous {
    border-color: #b7791f;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, #b7791f 55%, transparent);
  }
  .crop-img {
    display: block;
    width: 100%;
    height: auto;
  }
  .option-marker {
    position: absolute;
    display: grid;
    place-items: center;
    font: 700 7px var(--font-mono, monospace);
    color: rgba(255, 255, 255, 0.6);
    text-shadow: 0 0 2px #000, 0 0 3px #000;
    pointer-events: none;
  }
  .option-marker.correct { color: #3fdd96; }
  .option-marker.incorrect { color: #ff8f8c; }
  .option-marker.ambiguous { color: #ffd27a; }
  .crop-note {
    position: relative;
    z-index: 1;
    padding: 0 4px;
    text-align: center;
    line-height: 1.3;
  }
</style>
