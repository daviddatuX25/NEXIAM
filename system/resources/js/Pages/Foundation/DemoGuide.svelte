<script>
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import { Label } from '@/Components/ui/label';
  import { Badge } from '@/Components/ui/badge';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import DemoReferenceViewer from './DemoReferenceViewer.svelte';
  import PracticePreparationPanel from './PracticePreparationPanel.svelte';
  import { Link } from '@inertiajs/svelte';
  import { csrfHeaders } from '@/lib/utils.js';
  import {
    ArrowLeft,
    ArrowDown,
    ArrowRight,
    BookOpen,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    ExternalLink,
    FileText,
    Key,
    RotateCcw,
    Shield,
    Users,
    Wrench,
    Zap,
    Sparkles,
    RefreshCw,
    Terminal,
  } from 'lucide-svelte';

  let {
    workflows = [],
    materials = [],
    can_control = false,
    preparation_url = null,
    persona_directory = null,
    pipeline_generator = null,
  } = $props();

  // Generator state
  let count = $state(10);
  let status = $state('pending');
  let isGenerating = $state(false);
  let generateMessage = $state('');
  let generateError = $state('');
  let generatedList = $state([]);
  let copiedKeyId = $state('');
  let copiedCli = $state(false);
  let searchFilter = $state('');
  let generatorInitialized = false;

  $effect(() => {
    if (!generatorInitialized && pipeline_generator) {
      count = pipeline_generator.default_count ?? 10;
      generatedList = [...(pipeline_generator.recent_attempts ?? [])];
      generatorInitialized = true;
    }
  });
  // Fallback workflow state (for unit tests and capstone guide)
  let selectedWorkflowKey = $state('application-review');
  let selectedStepIndex = $state(0);
  let activeTab = $state('before');
  let operatorOpen = $state(false);
  let directoryOpen = $state(false);
  let referenceOpen = $state(false);
  let selectedReference = $state(null);
  let inlineTriggerBusy = $state(false);
  let inlineTriggerStatus = $state('');
  let inlineTriggerError = $state('');

  const workflowList = $derived(Array.isArray(workflows) ? workflows : []);
  const materialList = $derived(Array.isArray(materials) ? materials : []);
  const selectedWorkflow = $derived(
    workflowList.find((workflow) => workflow.key === selectedWorkflowKey) ?? workflowList[0] ?? null
  );
  const selectedStep = $derived(selectedWorkflow?.steps?.[selectedStepIndex] ?? null);
  const activePersona = $derived(selectedStep?.actor_persona ?? null);

  const filteredApplicants = $derived(
    generatedList.filter((app) => {
      if (!searchFilter.trim()) return true;
      const q = searchFilter.toLowerCase();
      return (
        (app.full_name ?? '').toLowerCase().includes(q) ||
        (app.reference_code ?? '').toLowerCase().includes(q) ||
        (app.strand ?? '').toLowerCase().includes(q) ||
        (app.programs ?? []).some((p) => p.toLowerCase().includes(q))
      );
    })
  );

  async function handleGenerate() {
    if (isGenerating || count < 1) return;
    isGenerating = true;
    generateMessage = '';
    generateError = '';

    try {
      const response = await fetch(pipeline_generator?.generate_url ?? '/demo/generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...csrfHeaders(),
        },
        body: JSON.stringify({
          count: Number(count),
          status,
        }),
      });

      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message ?? 'Failed to generate applicants.');
      }

      const newApplicants = data.data?.applicants ?? [];
      generatedList = [...newApplicants, ...generatedList];
      generateMessage = `✓ Successfully generated ${newApplicants.length} applicant(s) with readable names!`;
    } catch (err) {
      generateError = err.message || 'Error generating applicants.';
    } finally {
      isGenerating = false;
    }
  }

  async function copyText(text, id) {
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      if (id === 'cli') {
        copiedCli = true;
        setTimeout(() => { copiedCli = false; }, 2000);
      } else {
        copiedKeyId = id;
        setTimeout(() => { copiedKeyId = ''; }, 2000);
      }
    } catch {
      // Browser permissions fallback
    }
  }

  function selectStep(index) {
    if (!selectedWorkflow?.steps || index < 0 || index >= selectedWorkflow.steps.length) return;
    selectedStepIndex = index;
    activeTab = 'before';
  }

  function selectWorkflow(event) {
    selectedWorkflowKey = event.currentTarget.value;
    selectedStepIndex = 0;
    activeTab = 'before';
  }
</script>

<svelte:head>
  <title>Demo Pipeline · Application Generator · NEXIAM</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <ToastManager />

  {#if pipeline_generator !== null}
    <!-- Dedicated Demo Pipeline Application Generator Surface -->
    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <!-- Header -->
      <header class="flex flex-col gap-4 border-b border-slate-800 pb-7 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <Badge variant="outline" class="border-blue-400/40 bg-blue-500/10 text-blue-300">
              Demo Admission Pipeline
            </Badge>
            {#if pipeline_generator.active_cycle}
              <Badge variant="outline" class="border-emerald-400/40 bg-emerald-500/10 text-emerald-300">
                Cycle: {pipeline_generator.active_cycle.code}
              </Badge>
            {/if}
          </div>
          <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Demo Pipeline · Application Generator</h1>
          <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">
            Choose how many applicants to autogenerate for the admissions pipeline with realistic Filipino identities, program preferences, and credentials.
          </p>
        </div>

        <nav class="flex flex-wrap items-center gap-2" aria-label="System links">
          <Link
            href="/admissions"
            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
          >
            <Users class="size-3 text-blue-300" aria-hidden="true" /> Open Admissions
          </Link>
          <Link
            href="/applicant/login"
            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
          >
            <Key class="size-3 text-emerald-300" aria-hidden="true" /> Applicant Portal
          </Link>
          <Link
            href="/"
            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
          >
            <ExternalLink class="size-3 text-slate-400" aria-hidden="true" /> Home
          </Link>
        </nav>
      </header>

      <!-- Autogenerate Controls Card -->
      <section class="mt-8 rounded-xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl backdrop-blur sm:p-8" aria-labelledby="generator-heading">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 id="generator-heading" class="text-lg font-semibold text-white">Choose how many applicants to autogenerate</h2>
            <p class="mt-1 text-sm text-slate-400">
              Generated applicants use authentic Filipino names, Region I high schools, SHS strands, and canonical Tagudin course choices.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-400">Total in cycle:</span>
            <Badge variant="secondary" class="bg-slate-800 text-sm font-semibold text-white">
              {pipeline_generator.total_attempts + (generatedList.length - (pipeline_generator.recent_attempts?.length ?? 0))}
            </Badge>
          </div>
        </div>

        <!-- Count Selector & Options -->
        <div class="mt-6 flex flex-wrap items-center gap-3">
          <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Presets:</span>
          {#each [3, 5, 10, 25, 50] as preset}
            <button
              type="button"
              class="rounded-lg border px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 {count === preset ? 'border-blue-500 bg-blue-600/20 text-blue-200 font-semibold shadow-sm' : 'border-slate-800 bg-slate-950/60 text-slate-300 hover:border-slate-700 hover:text-white'}"
              onclick={() => { count = preset; }}
            >
              {preset} applicants
            </button>
          {/each}

          <div class="flex items-center gap-2 sm:ml-auto">
            <Label for="custom-count" class="text-xs text-slate-400">Custom count:</Label>
            <input
              id="custom-count"
              type="number"
              min="1"
              max="100"
              bind:value={count}
              class="w-20 rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-center text-sm font-semibold text-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Initial Status Selector -->
        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-800/80 pt-5">
          <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Initial State:</span>
          <button
            type="button"
            class="rounded-lg border px-3 py-1.5 text-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 {status === 'pending' ? 'border-amber-500/50 bg-amber-500/10 text-amber-200 font-semibold' : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:text-white'}"
            onclick={() => { status = 'pending'; }}
          >
            Pending Review (Fresh Intake)
          </button>
          <button
            type="button"
            class="rounded-lg border px-3 py-1.5 text-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 {status === 'accepted' ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-200 font-semibold' : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:text-white'}"
            onclick={() => { status = 'accepted'; }}
          >
            Accepted (Ready for Scheduling)
          </button>

          <!-- Primary CTA Button -->
          <div class="w-full sm:w-auto sm:ml-auto pt-2 sm:pt-0">
            <Button
              type="button"
              disabled={isGenerating || count < 1}
              onclick={handleGenerate}
              class="w-full sm:w-auto gap-2 bg-blue-600 px-6 font-semibold text-white hover:bg-blue-500 focus-visible:ring-2 focus-visible:ring-blue-400 disabled:opacity-50"
            >
              {#if isGenerating}
                <RefreshCw class="size-4 animate-spin" aria-hidden="true" />
                Generating {count} applicants…
              {:else}
                <Sparkles class="size-4" aria-hidden="true" />
                Generate {count} Applicants
              {/if}
            </Button>
          </div>
        </div>

        <!-- Notifications -->
        {#if generateMessage}
          <div class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-200" role="status">
            <CheckCircle2 class="size-4 text-emerald-400 shrink-0" aria-hidden="true" />
            <span>{generateMessage}</span>
          </div>
        {/if}

        {#if generateError}
          <div class="mt-4 flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200" role="alert">
            <Shield class="size-4 text-rose-400 shrink-0" aria-hidden="true" />
            <span>{generateError}</span>
          </div>
        {/if}
      </section>

      <!-- Terminal Command Snippet Box -->
      <section class="mt-6 rounded-lg border border-slate-800/80 bg-slate-950/80 p-4" aria-label="Artisan command reference">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-2 text-xs font-medium text-slate-400">
            <Terminal class="size-4 text-blue-400 shrink-0" aria-hidden="true" />
            <span>Or run from your terminal via Artisan:</span>
          </div>
          <div class="flex items-center gap-2">
            <code class="rounded bg-slate-900 px-2.5 py-1 text-xs font-mono text-blue-300">
              php artisan demo:seed-pipeline --count={count}
            </code>
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-7 gap-1 border-slate-700 bg-slate-900 px-2 text-xs text-slate-300 hover:bg-slate-800"
              onclick={() => copyText(`php artisan demo:seed-pipeline --count=${count}`, 'cli')}
            >
              {#if copiedCli}
                <Check class="size-3 text-emerald-400" /> Copied
              {:else}
                <Copy class="size-3" /> Copy
              {/if}
            </Button>
          </div>
        </div>
      </section>

      <!-- Applicants List -->
      <section class="mt-10" aria-labelledby="applicants-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 id="applicants-heading" class="text-xl font-bold tracking-tight text-white">Generated Applicants</h2>
            <p class="text-xs text-slate-400">Recent applications in the pipeline. Click on any record to view in admissions.</p>
          </div>

          <div class="w-full sm:w-64">
            <input
              type="search"
              placeholder="Search by name, ref, program…"
              bind:value={searchFilter}
              class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs text-slate-200 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>

        {#if filteredApplicants.length === 0}
          <div class="mt-4 rounded-xl border border-slate-800/80 bg-slate-900/40 p-12 text-center">
            <Users class="mx-auto size-8 text-slate-600" aria-hidden="true" />
            <h3 class="mt-2 text-sm font-semibold text-slate-300">No applicants found</h3>
            <p class="mt-1 text-xs text-slate-500">Choose a count above and click Generate Applicants to populate the pipeline.</p>
          </div>
        {:else}
          <div class="mt-4 overflow-hidden rounded-xl border border-slate-800 bg-slate-900/50 shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  <tr>
                    <th scope="col" class="px-4 py-3">Reference</th>
                    <th scope="col" class="px-4 py-3">Full Name</th>
                    <th scope="col" class="px-4 py-3">Programs</th>
                    <th scope="col" class="px-4 py-3">Strand & School</th>
                    <th scope="col" class="px-4 py-3">Access Key</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                  {#each filteredApplicants as applicant (applicant.id)}
                    <tr class="transition hover:bg-slate-800/40">
                      <td class="whitespace-nowrap px-4 py-3">
                        <span class="font-mono font-semibold text-blue-300">{applicant.reference_code}</span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-medium text-white">{applicant.full_name}</div>
                        <div class="text-[11px] text-slate-400">
                          {applicant.sex === 'female' ? 'Female' : 'Male'} · Born {applicant.birthdate}
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                          {#each (applicant.programs ?? []) as prog}
                            <Badge variant="outline" class="border-slate-700 bg-slate-800/80 px-1.5 py-0 text-[10px] text-slate-200">
                              {prog}
                            </Badge>
                          {/each}
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="text-slate-300 font-medium">{applicant.strand}</div>
                        <div class="truncate max-w-[180px] text-[11px] text-slate-500">{applicant.school}</div>
                      </td>
                      <td class="whitespace-nowrap px-4 py-3">
                        {#if applicant.access_key}
                          <div class="flex items-center gap-1.5">
                            <code class="rounded bg-slate-950 px-1.5 py-0.5 font-mono text-[11px] text-emerald-300">
                              {applicant.access_key}
                            </code>
                            <button
                              type="button"
                              aria-label="Copy access key for {applicant.full_name}"
                              class="text-slate-400 hover:text-white"
                              onclick={() => copyText(applicant.access_key, applicant.id)}
                            >
                              {#if copiedKeyId === applicant.id}
                                <Check class="size-3.5 text-emerald-400" />
                              {:else}
                                <Copy class="size-3.5" />
                              {/if}
                            </button>
                          </div>
                        {:else}
                          <span class="text-slate-500">—</span>
                        {/if}
                      </td>
                      <td class="whitespace-nowrap px-4 py-3">
                        {#if applicant.status === 'accepted'}
                          <Badge variant="outline" class="border-emerald-500/40 bg-emerald-500/10 text-emerald-300">
                            Accepted
                          </Badge>
                        {:else if applicant.status === 'pending'}
                          <Badge variant="outline" class="border-amber-500/40 bg-amber-500/10 text-amber-300">
                            Pending Review
                          </Badge>
                        {:else}
                          <Badge variant="outline" class="border-slate-700 bg-slate-800 text-slate-300">
                            {applicant.status}
                          </Badge>
                        {/if}
                      </td>
                      <td class="whitespace-nowrap px-4 py-3 text-right">
                        <Link
                          href={applicant.view_url ?? `/admissions/${applicant.id}`}
                          class="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 hover:underline"
                        >
                          View <ArrowRight class="size-3" />
                        </Link>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </div>
        {/if}
      </section>
    </main>
  {:else}
    <!-- Fallback Workflows Deck for Capstone Reader / Unit Tests -->
    <main class="mx-auto w-full max-w-6xl px-4 py-7 sm:px-8 sm:py-10 lg:px-10">
      <header class="flex flex-col gap-5 border-b border-slate-800 pb-7 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <Badge variant="outline" class="border-blue-400/40 bg-blue-500/10 text-blue-300">
              Developer Meta Guide
            </Badge>
          </div>
          <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">System Workflows & Development Deck</h1>
          <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-400">
            Step-by-step guides, verified actor credentials, and pipeline controls for local development and testing.
          </p>
        </div>

        <nav class="flex flex-wrap items-center gap-2" aria-label="Quick links">
          <Link href="/admissions" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200">
            Open Admissions
          </Link>
        </nav>
      </header>

      <div class="mt-6">
        <label for="workflow-select" class="block text-xs font-medium text-slate-400">Choose a workflow</label>
        <select
          id="workflow-select"
          value={selectedWorkflowKey}
          onchange={selectWorkflow}
          class="mt-1 block w-full rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs text-white"
        >
          {#each workflowList as wf}
            <option value={wf.key}>{wf.title}</option>
          {/each}
        </select>
      </div>

      {#if selectedWorkflow}
        <section class="mt-8">
          <div class="flex items-center gap-4">
            <button
              type="button"
              disabled={selectedStepIndex === 0}
              onclick={() => selectStep(selectedStepIndex - 1)}
              class="rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
            >
              Previous
            </button>
            <button
              type="button"
              aria-pressed={activeTab === 'before' ? 'true' : 'false'}
              onclick={() => { activeTab = 'before'; }}
              class="rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              Before
            </button>
            <button
              type="button"
              aria-pressed={activeTab === 'actions' ? 'true' : 'false'}
              onclick={() => { activeTab = 'actions'; }}
              class="rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              Steps
            </button>
            <button
              type="button"
              aria-pressed={activeTab === 'handoff' ? 'true' : 'false'}
              onclick={() => { activeTab = 'handoff'; }}
              class="rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              After
            </button>
            <button
              type="button"
              disabled={selectedStepIndex >= (selectedWorkflow.steps?.length ?? 1) - 1}
              onclick={() => selectStep(selectedStepIndex + 1)}
              class="rounded border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
            >
              Next
            </button>
          </div>

          {#if selectedStep}
            <div class="mt-6 rounded-lg border border-slate-800 bg-slate-900/60 p-6">
              <h3 class="text-lg font-semibold text-white">{selectedStep.title}</h3>
              {#if activeTab === 'before'}
                <div class="mt-4 text-sm text-slate-300">{selectedStep.before}</div>
              {:else if activeTab === 'actions'}
                <div class="mt-4 space-y-2 text-sm text-slate-300">
                  {#each (selectedStep.actions ?? []) as action}
                    <div>{action}</div>
                  {/each}
                </div>
              {:else if activeTab === 'handoff'}
                <div class="mt-4 text-sm text-slate-300">{selectedStep.expected}</div>
              {/if}
            </div>
          {/if}
        </section>
      {/if}
    </main>
  {/if}
</div>
