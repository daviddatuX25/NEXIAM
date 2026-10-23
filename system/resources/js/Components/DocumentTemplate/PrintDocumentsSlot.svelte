<script>
  import { onDestroy, onMount } from 'svelte';
  import { usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Printer, ChevronDown, FileText } from 'lucide-svelte';
  import DocumentPrintManagerModal from './DocumentPrintManagerModal.svelte';

  let {
    slotKey = '',
    attemptIds = [],
    sessionId = null,
    sessionKind = 'exam',
    cycleId = null,
    academicCycleId = null,
    programCode = null,
    departmentCode = null,
    autoStart = false,
    compact = false,
    buttonVariant = 'outline',
    buttonSize = 'sm',
    class: className = '',
    label = null,
  } = $props();

  const page = usePage();
  const slotTemplates = $derived(page?.props?.mounted_document_slots?.[slotKey] ?? []);

  let modalOpen = $state(false);
  let selectedTemplate = $state(null);
  let dropdownOpen = $state(false);
  let dropdownContainer = $state(null);

  function shortName(name) {
    const normalizedName = (name ?? '')
      .trim()
      .replace(/^Standard\s+/i, '')
      .replace(/\s*\(System Default\)$/i, '')
      .replace(/\s+/g, ' ')
      .trim();

    return normalizedName || 'Document';
  }

  function openTemplateModal(tpl) {
    selectedTemplate = tpl;
    dropdownOpen = false;
    modalOpen = true;
  }

  function toggleDropdown() {
    dropdownOpen = !dropdownOpen;
  }

  function handleClickOutside(event) {
    if (dropdownOpen && dropdownContainer && !dropdownContainer.contains(event.target)) {
      dropdownOpen = false;
    }
  }

  function handleKeydown(event) {
    if (dropdownOpen && event.key === 'Escape') {
      dropdownOpen = false;
    }
  }

  onMount(() => {
    if (typeof window !== 'undefined') {
      window.addEventListener('click', handleClickOutside);
      window.addEventListener('keydown', handleKeydown);
    }
  });

  onDestroy(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('click', handleClickOutside);
      window.removeEventListener('keydown', handleKeydown);
    }
  });
</script>

{#if slotTemplates.length > 0}
  {#if slotTemplates.length === 1}
    {#if compact}
      <Button
        variant={buttonVariant}
        size={buttonSize}
        class="gap-1.5 {className}"
        onclick={() => openTemplateModal(slotTemplates[0])}
      >
        <Printer class="size-3.5 shrink-0" />
        <span>{label ?? 'Print'}</span>
      </Button>
    {:else}
      <Button
        variant={buttonVariant}
        size={buttonSize}
        class="gap-1.5 {className}"
        onclick={() => openTemplateModal(slotTemplates[0])}
      >
        <Printer class="size-3.5 shrink-0" />
        <span class="max-w-[150px] sm:max-w-[200px] truncate" title={slotTemplates[0].name}>
          {label ?? shortName(slotTemplates[0].name)}
        </span>
      </Button>
    {/if}
  {:else}
    <div class="relative inline-block text-left {className}" bind:this={dropdownContainer}>
      <Button
        variant={buttonVariant}
        size={buttonSize}
        class="gap-1.5"
        aria-haspopup="menu"
        aria-expanded={dropdownOpen}
        onclick={toggleDropdown}
      >
        <Printer class="size-3.5 shrink-0" />
        <span>{label ?? 'Print'}</span>
        <ChevronDown class="size-3.5 shrink-0 opacity-70" />
      </Button>

      {#if dropdownOpen}
        <div
          class="absolute right-0 z-50 mt-1.5 w-72 max-w-[calc(100vw-2rem)] origin-top-right rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-lg ring-1 ring-black/5 focus:outline-none"
          role="menu"
          aria-orientation="vertical"
        >
          {#each slotTemplates as tpl (tpl.id)}
            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left text-xs font-medium text-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
              onclick={() => openTemplateModal(tpl)}
            >
              <span class="flex min-w-0 flex-1 items-center gap-2">
                <FileText class="size-3.5 shrink-0 text-muted-foreground" />
                <span class="min-w-0 truncate">Print {tpl.name}</span>
              </span>
              <span class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground uppercase">
                {tpl.grain === 'cycle_program' ? 'Report' : 'Slip'}
              </span>
            </button>
          {/each}
        </div>
      {/if}
    </div>
  {/if}
  <DocumentPrintManagerModal
    bind:open={modalOpen}
    template={selectedTemplate ?? slotTemplates[0]}
    templates={slotTemplates}
    {attemptIds}
    {sessionId}
    {sessionKind}
    {cycleId}
    academicCycleId={academicCycleId ?? cycleId}
    {programCode}
    {departmentCode}
    autoStartSingle={autoStart && attemptIds.length === 1}
  />
{/if}

