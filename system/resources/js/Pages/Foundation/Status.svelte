<script>
  import { Link } from '@inertiajs/svelte';
  import { Badge } from '@/Components/ui/badge';

  let { foundation } = $props();
</script>

{#snippet item(label, value)}
  <div class="rounded-lg border border-slate-800 bg-slate-950/70 p-5">
    <dt class="text-sm text-slate-400">{label}</dt>
    <dd class="mt-2 font-medium text-white">{value}</dd>
  </div>
{/snippet}

<svelte:head>
  <title>Foundation status</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto flex min-h-screen max-w-5xl items-center px-6 py-16 lg:px-10">
    <section class="w-full rounded-xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-slate-950/40 lg:p-12">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-sm font-semibold tracking-[0.24em] text-blue-300 uppercase">
            NEXIAM system
          </p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-5xl">
            Foundation runtime is alive.
          </h1>
        </div>
        <Badge class="border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-300">
          {foundation.phase}
        </Badge>
      </div>
      <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300">
        E1b flips the isolated Laravel, Inertia, Svelte, PostgreSQL, and
        health boundary onto the legacy-evidenced frontend stack before
        domain feature work resumes.
      </p>
      <dl class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {@render item('Artifact', foundation.artifact)}
        {@render item('Runtime', foundation.runtime)}
        {@render item('Database', foundation.database)}
        <div class="rounded-lg border border-slate-800 bg-slate-950/70 p-5 sm:col-span-2 lg:col-span-3">
          <dt class="text-sm text-slate-400">Legacy boundary</dt>
          <dd class="mt-2 font-medium text-white">{foundation.legacyReference}</dd>
        </div>
      </dl>
      <div class="mt-10 flex flex-wrap gap-3 text-sm">
        <Link class="rounded-lg bg-blue-500 px-4 py-2 font-semibold text-white" href="/health">
          Check runtime health
        </Link>
        <a class="rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-200" href="/up">
          Laravel readiness probe
        </a>
      </div>
    </section>
  </main>
</div>
