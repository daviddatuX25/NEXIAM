<script>
  import { Search, ChevronDown, Check } from 'lucide-svelte';

  let {
    value = $bindable('Inter, sans-serif'),
    label = 'Font Family',
    compact = false,
    onChanged = null,
  } = $props();

  let isOpen = $state(false);
  let searchQuery = $state('');
  let selectedCategory = $state('all'); // 'all' | 'sans' | 'serif' | 'mono' | 'display'

  const FONTS = [
    { name: 'Inter', value: 'Inter, system-ui, sans-serif', category: 'sans', sample: 'Clean Modern Sans' },
    { name: 'Arial', value: 'Arial, sans-serif', category: 'sans', sample: 'Standard Universal' },
    { name: 'Helvetica', value: 'Helvetica, Arial, sans-serif', category: 'sans', sample: 'Swiss Typography' },
    { name: 'Roboto', value: 'Roboto, sans-serif', category: 'sans', sample: 'Geometric Neo-Grotesque' },
    { name: 'Times New Roman', value: '"Times New Roman", Times, serif', category: 'serif', sample: 'Formal Academic' },
    { name: 'Georgia', value: 'Georgia, serif', category: 'serif', sample: 'Editorial Warm Serif' },
    { name: 'Garamond', value: 'Garamond, "EB Garamond", serif', category: 'serif', sample: 'Classic Literary' },
    { name: 'Courier New', value: '"Courier New", Courier, monospace', category: 'mono', sample: 'Typewriter Monospace' },
    { name: 'JetBrains Mono', value: '"JetBrains Mono", monospace', category: 'mono', sample: 'Modern Technical Mono' },
    { name: 'Oswald', value: 'Oswald, sans-serif', category: 'display', sample: 'CONDENSED DISPLAY' },
    { name: 'Playfair Display', value: '"Playfair Display", serif', category: 'display', sample: 'Elegant Headline' },
  ];

  let filteredFonts = $derived(
    FONTS.filter((f) => {
      const matchesCat = selectedCategory === 'all' || f.category === selectedCategory;
      const matchesSearch = !searchQuery || f.name.toLowerCase().includes(searchQuery.toLowerCase()) || f.category.toLowerCase().includes(searchQuery.toLowerCase());
      return matchesCat && matchesSearch;
    })
  );

  let currentFontName = $derived(
    FONTS.find((f) => f.value === value || value?.startsWith(f.name))?.name || value?.split(',')[0]?.replace(/["']/g, '') || 'Default'
  );

  function selectFont(fontValue) {
    value = fontValue;
    isOpen = false;
    searchQuery = '';
    if (onChanged) onChanged();
  }
</script>

<div class="relative w-full">
  {#if label && !compact}
    <label class="text-[11px] font-semibold text-slate-700 block mb-1">{label}</label>
  {/if}

  <!-- Trigger Button -->
  <button
    type="button"
    onclick={() => { isOpen = !isOpen; }}
    class={`w-full flex items-center justify-between bg-white border border-slate-200 rounded-lg text-left transition shadow-2xs hover:border-slate-300 ${
      compact ? 'px-2 py-1 text-[11px]' : 'px-2.5 py-1.5 text-xs'
    }`}
  >
    <div class="flex items-center space-x-2 truncate">
      <span class="font-medium text-slate-900 truncate" style={`font-family: ${value};`}>
        {currentFontName}
      </span>
    </div>
    <ChevronDown class="size-3.5 text-slate-400 shrink-0 ml-1" />
  </button>

  <!-- Dropdown Menu with Live Search -->
  {#if isOpen}
    <!-- Click Outside Overlay -->
    <div
      class="fixed inset-0 z-40"
      onclick={() => { isOpen = false; }}
      role="presentation"
    ></div>

    <div
      class="absolute left-0 top-full mt-1 w-64 bg-white rounded-xl border border-slate-200 shadow-2xl z-50 p-2 space-y-2 text-xs animate-in fade-in zoom-in-95 duration-100"
    >
      <!-- Search Input -->
      <div class="relative">
        <Search class="size-3.5 absolute left-2.5 top-2 text-slate-400" />
        <input
          type="text"
          bind:value={searchQuery}
          placeholder="Search font (e.g. Times, Mono)…"
          class="w-full pl-8 pr-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-[11px] placeholder:text-slate-400 focus:outline-hidden focus:ring-1 focus:ring-slate-900"
          autofocus
        />
      </div>

      <!-- Category Filter Pills -->
      <div class="flex items-center space-x-1 overflow-x-auto pb-0.5">
        {#each [
          { id: 'all', label: 'All' },
          { id: 'sans', label: 'Sans' },
          { id: 'serif', label: 'Serif' },
          { id: 'mono', label: 'Mono' },
          { id: 'display', label: 'Display' },
        ] as cat}
          <button
            type="button"
            onclick={() => { selectedCategory = cat.id; }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold transition shrink-0 ${
              selectedCategory === cat.id
                ? 'bg-slate-900 text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            {cat.label}
          </button>
        {/each}
      </div>

      <!-- Font List -->
      <div class="max-h-48 overflow-y-auto space-y-0.5 pr-0.5">
        {#if filteredFonts.length === 0}
          <div class="py-4 text-center text-[11px] text-slate-400 font-medium">
            No matching fonts found
          </div>
        {:else}
          {#each filteredFonts as font}
            {@const isSelected = value === font.value || value?.startsWith(font.name)}
            <button
              type="button"
              onclick={() => selectFont(font.value)}
              class={`w-full text-left px-2.5 py-1.5 rounded-lg flex items-center justify-between transition ${
                isSelected
                  ? 'bg-emerald-50 text-emerald-950 font-semibold'
                  : 'hover:bg-slate-50 text-slate-800'
              }`}
            >
              <div class="truncate">
                <div class="text-xs truncate" style={`font-family: ${font.value};`}>
                  {font.name}
                </div>
                <div class="text-[9.5px] text-slate-400 truncate" style={`font-family: ${font.value};`}>
                  {font.sample}
                </div>
              </div>
              {#if isSelected}
                <Check class="size-3.5 text-emerald-600 shrink-0 ml-2" />
              {/if}
            </button>
          {/each}
        {/if}
      </div>
    </div>
  {/if}
</div>
