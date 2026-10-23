<script>
  import { Button } from '@/Components/ui/button';
  import DemoReferenceViewer from './DemoReferenceViewer.svelte';
  import { safeMarkdownHtml } from '@/lib/safeMarkdownHtml';
  import { router } from '@inertiajs/svelte';
  import { ArrowLeft, ArrowRight, BookOpen, ClipboardList, FileText } from 'lucide-svelte';

  let {
    groups = [],
    selected_role = 'student',
    participant = {},
    questionnaire = {},
    after_instructions = '',
  } = $props();

  let section = $state('before');
  let referenceOpen = $state(false);
  let selectedReference = $state(null);
  let taskIndex = $state(0);

  const roleGroups = $derived(Array.isArray(groups) ? groups : []);
  const currentTasks = $derived(Array.isArray(participant?.tasks) ? participant.tasks : []);
  const currentTask = $derived(currentTasks[taskIndex] ?? null);
  const instructionHtml = $derived(safeMarkdownHtml(String(after_instructions ?? '')));

  $effect(() => {
    const tasks = currentTasks;
    const selectedTask = participant?.selected_task;
    const selectedIndex = tasks.findIndex((task) => task.id === selectedTask);
    taskIndex = selectedIndex >= 0 ? selectedIndex : 0;
  });

  function changeRole(event) {
    const role = event.currentTarget.value;
    if (!roleGroups.some((group) => group.key === role) || role === selected_role) return;

    router.get('/demo/research', { role }, { preserveState: false, preserveScroll: false });
  }

  function moveTask(direction) {
    const nextIndex = taskIndex + direction;
    if (nextIndex < 0 || nextIndex >= currentTasks.length) return;
    taskIndex = nextIndex;
  }

  function openQuestionnaire() {
    selectedReference = {
      ...questionnaire,
      kind: 'material',
      note: questionnaire?.note ?? 'Read-only research instrument. No responses are collected or saved here.',
    };
    referenceOpen = true;
  }
</script>

<svelte:head>
  <title>Research session guide · NEXIAM</title>
  <meta name="description" content="Read the source-bound research session instructions for your assigned participant role." />
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-7 sm:py-10 lg:px-10">
    <header class="border-b border-slate-800 pb-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Research session</p>
          <h1 class="mt-2 break-words text-3xl font-semibold tracking-tight text-white sm:text-4xl">{participant?.title ?? 'Session guide'}</h1>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Read the assigned instructions before using the separately provided research workspace. This guide does not change access or record responses.</p>
        </div>
        <div class="w-full shrink-0 sm:w-64">
          <label for="research-role" class="text-sm font-semibold text-slate-200">Participant role</label>
          <select id="research-role" value={selected_role} onchange={changeRole} class="mt-2 flex min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
            {#each roleGroups as group (group.key)}
              <option value={group.key}>{group.label}</option>
            {/each}
          </select>
        </div>
      </div>
      <div class="mt-5 flex flex-wrap items-center gap-2" role="group" aria-label="Research guide sections">
        <button type="button" aria-pressed={section === 'before'} class={`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === 'before' ? 'border-blue-400/50 bg-blue-500/15 text-blue-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'}`} onclick={() => (section = 'before')}><BookOpen class="size-4" aria-hidden="true" /> Before</button>
        <button type="button" aria-pressed={section === 'tasks'} class={`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === 'tasks' ? 'border-blue-400/50 bg-blue-500/15 text-blue-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'}`} onclick={() => (section = 'tasks')}><ClipboardList class="size-4" aria-hidden="true" /> Tasks</button>
        <button type="button" aria-pressed={section === 'after'} class={`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === 'after' ? 'border-blue-400/50 bg-blue-500/15 text-blue-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'}`} onclick={() => (section = 'after')}><FileText class="size-4" aria-hidden="true" /> After</button>
      </div>
    </header>

    {#if section === 'before'}
      <section class="mt-6 space-y-4" aria-labelledby="before-heading">
        <h2 id="before-heading" class="text-xl font-semibold text-white">Before you begin</h2>
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-blue-200">Standard briefing</h3>
          <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">{participant?.standard_briefing ?? ''}</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-blue-200">Role briefing</h3>
          <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">{participant?.briefing ?? ''}</p>
        </div>
        <div class="rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 sm:p-6">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-100">Required context</h3>
          <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">{participant?.context ?? ''}</p>
          {#if participant?.restrictions}
            <p class="mt-4 border-t border-amber-200/10 pt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-300">{participant.restrictions}</p>
          {/if}
        </div>
        <div class="flex justify-end pt-1">
          <Button type="button" variant="outline" class="min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" onclick={() => (section = 'tasks')}>Continue to tasks <ArrowRight class="ml-2 size-4" aria-hidden="true" /></Button>
        </div>
      </section>
    {:else if section === 'tasks'}
      <section class="mt-6" aria-labelledby="tasks-heading">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Assigned activity</p>
            <h2 id="tasks-heading" class="mt-1 text-xl font-semibold text-white">Research tasks</h2>
          </div>
          {#if currentTask}
            <p class="text-sm tabular-nums text-slate-400">Task {taskIndex + 1} of {currentTasks.length}</p>
          {/if}
        </div>
        {#if currentTask}
          <article class="mt-4 min-h-44 rounded-xl border border-slate-800 bg-slate-900/70 p-5 sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Task {currentTask.id}</p>
            <p class="mt-4 whitespace-pre-wrap break-words text-base leading-7 text-slate-100 sm:text-lg">{currentTask.text}</p>
          </article>
          <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Button type="button" variant="outline" class="min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto" disabled={taskIndex === 0} onclick={() => moveTask(-1)}><ArrowLeft class="mr-2 size-4" aria-hidden="true" /> Previous task</Button>
            <p class="text-center text-xs leading-5 text-slate-400">Task navigation changes only this displayed reference.</p>
            <Button type="button" variant="outline" class="min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto" disabled={taskIndex >= currentTasks.length - 1} onclick={() => moveTask(1)}>Next task <ArrowRight class="ml-2 size-4" aria-hidden="true" /></Button>
          </div>
        {:else}
          <p class="mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-5 text-sm leading-6 text-slate-300">No tasks are listed for this role in the published reference.</p>
        {/if}
        <div class="mt-6 flex justify-end">
          <Button type="button" variant="outline" class="min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" onclick={() => (section = 'after')}>Continue to after session <ArrowRight class="ml-2 size-4" aria-hidden="true" /></Button>
        </div>
      </section>
    {:else}
      <section class="mt-6 space-y-4" aria-labelledby="after-heading">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Read-only reference</p>
          <h2 id="after-heading" class="mt-1 text-xl font-semibold text-white">After the session</h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Use the canonical instructions below for blank, neutral, or failure observations. This page does not collect answers or store session notes.</p>
        </div>
        <article class="guide-research-markdown rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          {@html instructionHtml}
        </article>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <Button type="button" variant="outline" class="min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto" onclick={openQuestionnaire}>Open {questionnaire?.title ?? 'questionnaire'} <FileText class="ml-2 size-4" aria-hidden="true" /></Button>
          <a href={questionnaire?.url} download class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">Download blank questionnaire <FileText class="size-4" aria-hidden="true" /></a>
          <Button type="button" variant="outline" class="min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto" onclick={() => (section = 'before')}><ArrowLeft class="mr-2 size-4" aria-hidden="true" /> Back to before</Button>
        </div>
        <p class="text-xs leading-5 text-slate-500">{questionnaire?.note ?? ''}</p>
      </section>
    {/if}
  </main>
</div>

<DemoReferenceViewer bind:open={referenceOpen} reference={selectedReference} />

<style>
  :global(.guide-research-markdown) { color: #cbd5e1; font-size: 0.9375rem; line-height: 1.7; overflow-wrap: anywhere; }
  :global(.guide-research-markdown h1), :global(.guide-research-markdown h2), :global(.guide-research-markdown h3), :global(.guide-research-markdown h4) { color: #f1f5f9; font-weight: 600; line-height: 1.35; margin: 1.5rem 0 0.75rem; }
  :global(.guide-research-markdown h1) { font-size: 1.5rem; }
  :global(.guide-research-markdown h2) { font-size: 1.25rem; }
  :global(.guide-research-markdown p) { margin: 0.75rem 0; }
  :global(.guide-research-markdown ul), :global(.guide-research-markdown ol) { padding-left: 1.5rem; margin: 0.75rem 0; }
  :global(.guide-research-markdown ul) { list-style: disc; }
  :global(.guide-research-markdown ol) { list-style: decimal; }
  :global(.guide-research-markdown li) { margin: 0.375rem 0; }
  :global(.guide-research-markdown a) { color: #93c5fd; text-decoration: underline; text-underline-offset: 0.2em; }
  :global(.guide-research-markdown blockquote) { border-left: 1px solid #475569; padding-left: 1rem; margin: 1rem 0; }
  :global(.guide-research-markdown pre) { overflow-x: auto; padding: 1rem; background: #0f172a; border-radius: 0.5rem; }
  :global(.guide-research-markdown code) { font-family: var(--font-mono); font-size: 0.875em; }
</style>
