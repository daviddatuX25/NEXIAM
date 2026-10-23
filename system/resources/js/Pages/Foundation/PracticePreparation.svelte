<script>
  import { Link } from '@inertiajs/svelte';
  import PracticePreparationPanel from './PracticePreparationPanel.svelte';

  let { purpose = 'demo', scenario = 'application-review', scenarios = [], context = {}, sets = [], selected_set = null, materials = null, prerequisites = [], actions = [], preserved = [], endpoints = {} } = $props();
  const initial = $derived({ purpose, scenario, scenarios, context, sets, selected_set, materials, prerequisites, actions, preserved, endpoints });
</script>

<svelte:head><title>Practice preparation · NEXIAM</title></svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto w-full max-w-4xl px-4 py-7 sm:px-8 sm:py-10">
    <header class="border-b border-slate-800 pb-6">
      <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Practice preparation</h1>
      <nav class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Practice references">
        <Link href="/demo" class="inline-flex min-h-11 items-center text-blue-300 underline underline-offset-4 hover:text-blue-200 focus-visible:ring-2 focus-visible:ring-blue-400">Workflow guide</Link>
        <Link href="/demo/research/facilitator" class="inline-flex min-h-11 items-center text-blue-300 underline underline-offset-4 hover:text-blue-200 focus-visible:ring-2 focus-visible:ring-blue-400">Facilitator reference</Link>
      </nav>
    </header>
    <div class="mt-6"><PracticePreparationPanel {purpose} {scenario} {initial} can_control={true} index_url={endpoints.index ?? '/demo/preparation'} /></div>
  </main>
</div>
