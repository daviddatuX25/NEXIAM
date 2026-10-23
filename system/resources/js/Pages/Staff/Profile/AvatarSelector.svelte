<script>
  import { router } from '@inertiajs/svelte';
  import { ImagePlus, Upload, UserRound, Check, Sparkles } from 'lucide-svelte';
  import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';

  let { currentAvatar = { type: 'initials', preset: null, url: null }, name = '' } = $props();

  const presets = [
    { key: 'counselor', title: 'Guidance Counselor', office: 'Guidance Office' },
    { key: 'guidance_clerk', title: 'Guidance Clerk', office: 'Guidance Office' },
    { key: 'guidance_support', title: 'Guidance Support Staff', office: 'Guidance Office' },
    { key: 'registrar_admin', title: 'Registrar Administrator', office: 'Registrar' },
    { key: 'records', title: 'Registrar Clerk', office: 'Registrar' },
    { key: 'registrar_support', title: 'Registrar Support Staff', office: 'Registrar' },
    { key: 'program_head', title: 'Program Head', office: 'Academic Department' },
    { key: 'dean', title: 'Academic Dean', office: "Dean's Office" },
    { key: 'hr_officer', title: 'Human Resource Officer', office: 'Human Resources' },
    { key: 'exam_proctor', title: 'Test Administrator', office: 'Examination' },
  ];
  const modes = [
    { key: 'presets', label: 'Office SVG Presets' },
    { key: 'upload', label: 'Upload Photo' },
    { key: 'initials', label: 'Initials Monogram' },
  ];

  let mode = $state('presets');
  let photo = $state(null);
  let photoPreview = $state('');
  let dragOver = $state(false);
  let error = $state('');
  let saved = $state('');
  let processing = $state(false);

  const previewAvatar = $derived(photoPreview
    ? { type: 'upload', preset: null, url: photoPreview }
    : currentAvatar);

  function setMode(nextMode) {
    mode = nextMode;
    error = '';
    saved = '';
  }

  function clearPreview() {
    if (photoPreview) URL.revokeObjectURL(photoPreview);
    photoPreview = '';
    photo = null;
  }

  function choosePhoto(file) {
    error = '';
    saved = '';
    if (!file) return;
    clearPreview();
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      error = 'Choose a JPEG, PNG, or WebP image.';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      error = 'The photo must be 2 MB or smaller.';
      return;
    }
    photo = file;
    photoPreview = URL.createObjectURL(file);
  }

  function handleFileInput(event) {
    choosePhoto(event.currentTarget.files?.[0]);
    event.currentTarget.value = '';
  }

  function handleDrop(event) {
    event.preventDefault();
    dragOver = false;
    choosePhoto(event.dataTransfer.files?.[0]);
  }

  function selectPreset(key) {
    error = '';
    saved = '';
    processing = true;
    router.post('/staff/profile/avatar', { type: 'preset', preset: key }, {
      preserveScroll: true,
      onSuccess: () => { saved = 'Avatar updated.'; clearPreview(); },
      onError: (errors) => { error = Object.values(errors)[0] ?? 'The avatar could not be updated.'; },
      onFinish: () => { processing = false; },
    });
  }

  function submitPhoto() {
    if (!photo) {
      error = 'Choose a photo before uploading.';
      return;
    }
    error = '';
    saved = '';
    processing = true;
    router.post('/staff/profile/avatar', { type: 'upload', photo }, {
      preserveScroll: true,
      onSuccess: () => { saved = 'Photo uploaded.'; clearPreview(); },
      onError: (errors) => { error = Object.values(errors)[0] ?? 'The photo could not be uploaded.'; },
      onFinish: () => { processing = false; },
    });
  }

  function useInitials() {
    error = '';
    saved = '';
    processing = true;
    router.post('/staff/profile/avatar', { type: 'initials' }, {
      preserveScroll: true,
      onSuccess: () => { saved = 'Initials monogram restored.'; clearPreview(); },
      onError: (errors) => { error = Object.values(errors)[0] ?? 'The avatar could not be reset.'; },
      onFinish: () => { processing = false; },
    });
  }

  $effect(() => () => {
    if (photoPreview) URL.revokeObjectURL(photoPreview);
  });
</script>

<section class="rounded-xl border border-border bg-card p-6 shadow-sm" aria-labelledby="avatar-customizer-title">
  <div class="mb-5 flex flex-wrap items-center gap-4">
    <AvatarBadge avatar={previewAvatar} {name} size="xl" />
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <Sparkles class="size-4 text-primary" aria-hidden="true" />
        <h2 id="avatar-customizer-title" class="text-lg font-semibold text-foreground">Your staff avatar</h2>
      </div>
      <p class="mt-1 text-sm text-muted-foreground">Choose an office illustration or use a local profile photo.</p>
      <p class="mt-1 truncate text-sm font-medium text-foreground">{name || 'Staff member'}</p>
    </div>
  </div>

  <div class="mb-5 grid grid-cols-1 gap-2 rounded-lg bg-muted/50 p-1 sm:grid-cols-3" role="group" aria-label="Avatar type">
    {#each modes as item (item.key)}
      <button
        type="button"
        aria-pressed={mode === item.key}
        class="min-h-[44px] rounded-md px-3 py-2 text-sm font-medium transition-colors {mode === item.key ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
        onclick={() => setMode(item.key)}
      >{item.label}</button>
    {/each}
  </div>

  {#if mode === 'presets'}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
      {#each presets as preset (preset.key)}
        {@const selected = currentAvatar?.type === 'preset' && currentAvatar?.preset === preset.key}
        <button
          type="button"
          class="group flex min-h-32 flex-col items-center justify-center gap-2 rounded-xl border p-3 text-center transition {selected ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border bg-background/60 hover:border-primary/50 hover:bg-accent/50'}"
          aria-pressed={selected}
          aria-label="Choose {preset.title} avatar"
          disabled={processing}
          onclick={() => selectPreset(preset.key)}
        >
          <span class="relative">
            <AvatarBadge avatar={{ type: 'preset', preset: preset.key, url: null }} {name} size="lg" />
            {#if selected}<span class="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"><Check class="size-3" aria-hidden="true" /></span>{/if}
          </span>
          <span class="text-xs font-semibold leading-tight text-foreground">{preset.title}</span>
          <span class="text-[11px] text-muted-foreground">{preset.office}</span>
        </button>
      {/each}
    </div>
  {:else if mode === 'upload'}
    <div class="space-y-4">
      <div
        class="relative mx-auto flex aspect-square w-full max-w-xs flex-col items-center justify-center gap-3 overflow-hidden rounded-xl border-2 border-dashed p-5 text-center transition {dragOver ? 'border-primary bg-primary/5' : 'border-border bg-muted/20 hover:border-primary/50'}"
        ondragover={(event) => { event.preventDefault(); dragOver = true; }}
        ondragleave={() => { dragOver = false; }}
        ondrop={handleDrop}
      >
        {#if photoPreview}
          <img src={photoPreview} alt="Selected staff avatar preview" class="size-32 rounded-xl object-cover shadow-md" />
          <p class="max-w-full truncate text-sm font-medium">{photo?.name}</p>
          <button type="button" class="text-sm font-medium text-primary underline-offset-4 hover:underline" onclick={clearPreview}>Choose a different photo</button>
        {:else}
          <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"><ImagePlus class="size-6" aria-hidden="true" /></span>
          <div>
            <p class="font-semibold text-foreground">Drop a photo here</p>
            <p class="mt-1 text-sm text-muted-foreground">JPEG, PNG, or WebP · up to 2 MB</p>
          </div>
          <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-lg border border-border bg-card px-4 text-sm font-semibold text-foreground hover:bg-accent">
            <Upload class="size-4" aria-hidden="true" /> Browse files
            <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" onchange={handleFileInput} />
          </label>
        {/if}
      </div>
      <button type="button" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50" disabled={processing || !photo} onclick={submitPhoto}>
        <Upload class="size-4" aria-hidden="true" />{processing ? 'Uploading…' : 'Upload photo'}
      </button>
    </div>
  {:else}
    <div class="flex flex-col items-center gap-4 rounded-xl border border-border bg-muted/20 p-6 text-center">
      <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"><UserRound class="size-6" aria-hidden="true" /></span>
      <div>
        <p class="font-semibold text-foreground">Use your name as a monogram</p>
        <p class="mt-1 text-sm text-muted-foreground">Your initials stay crisp and consistent throughout the staff workspace.</p>
      </div>
      <button type="button" class="min-h-[44px] rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold hover:bg-accent disabled:opacity-50" disabled={processing || currentAvatar?.type === 'initials'} onclick={useInitials}>
        {processing ? 'Updating…' : 'Revert to initials'}
      </button>
    </div>
  {/if}

  {#if error}<p class="mt-4 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">{error}</p>{/if}
  {#if saved}<p class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm font-medium text-emerald-700 dark:text-emerald-300" role="status">{saved}</p>{/if}
</section>
