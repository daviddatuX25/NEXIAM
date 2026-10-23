<script lang="ts">
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { Link } from '@inertiajs/svelte';
  import { Download, LockKeyhole, Printer, ShieldCheck } from 'lucide-svelte';

  type Score = {
    area_code: string;
    area_name: string;
    score: number | string | null;
    scale: 'percentile_rank' | null;
    available: boolean;
  };

  let {
    page_state = 'denied',
    release = null,
    score_profile = null,
    can_download_pdf = false,
    is_advised = false,
    attempt = null,
  } = $props<{
    page_state: 'denied' | 'not_ready' | 'held_f2f' | 'preparing_pdf' | 'unavailable' | 'unlocked';
    release: { mode: string; release_day_copy?: boolean; released_at: string | null; version: number | null } | null;
    score_profile: Score[] | null;
    can_download_pdf: boolean;
    is_advised: boolean;
    attempt: string | null;
  }>();
  function formatScore(score: number | string | null, available: boolean, scale: Score['scale']): string {
    if (!available || score === null || score === '') return '—';
    const value = Number(score);
    if (!Number.isFinite(value)) return '—';
    return scale === 'percentile_rank'
      ? `${value.toFixed(1).replace(/\.0$/, '')} percentile rank`
      : `${value.toFixed(1).replace(/\.0$/, '')} / 100`;
  }

</script>

<ApplicantLayout title="My Results" breadcrumbs={[{ label: 'My Results' }]}>
  <div class="mx-auto max-w-4xl space-y-5">
    {#if page_state === 'unlocked'}
      <Card.Root class="overflow-hidden shadow-sm">
        <Card.Header class="border-b px-5 py-5 sm:px-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <Card.Title class="text-xl">Official Entrance Examination Result</Card.Title>
              <Card.Description class="">
                {#if attempt}Application reference: {attempt}{/if}
              </Card.Description>
            </div>
            <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium capitalize text-emerald-800 dark:text-emerald-300">
              {#if release?.release_day_copy || release?.mode === 'f2f'}Release Day · In person{:else}Online release{/if}
            </span>
          </div>
        </Card.Header>
        <Card.Content class="space-y-5 p-5 sm:p-6">
          <div class="flex items-start gap-3 rounded-lg border border-emerald-500/25 bg-emerald-500/5 p-4">
            <ShieldCheck class="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300" aria-hidden="true" />
            <div>
              <p class="font-medium">Your official result is available</p>
              <p class="mt-1 text-sm text-muted-foreground">
                These scores are the released examination record for your application.
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row">
            {#if can_download_pdf}
              <a href="/applicant/results/pdf?presentation=attachment" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <Download class="size-4" aria-hidden="true" />
                Download Official Result Sheet (PDF)
              </a>
              <a href="/applicant/results/pdf?presentation=inline" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <Printer class="size-4" aria-hidden="true" />
                Print Official Result Sheet
              </a>
            {/if}
          </div>

          <section aria-labelledby="aptitude-scores-heading" class="space-y-3">
            <div>
              <h2 id="aptitude-scores-heading" class="text-base font-semibold">Aptitude Areas</h2>
              <p class="text-sm text-muted-foreground">Scores are shown using the scale recorded in your released result.</p>
            </div>
            {#if score_profile?.length}
              <div class="overflow-x-auto rounded-md border">
                <Table.Root class="">
                  <Table.Header class="">
                    <Table.Row class="">
                      <Table.Head class="">Aptitude area</Table.Head>
                      <Table.Head class="text-right">Score</Table.Head>
                    </Table.Row>
                  </Table.Header>
                  <Table.Body class="">
                    {#each score_profile as item, index (index)}
                      <Table.Row class="">
                        <Table.Cell class="font-medium">{item.area_name}</Table.Cell>
                        <Table.Cell class="text-right tabular-nums">{formatScore(item.score, item.available, item.scale)}</Table.Cell>
                      </Table.Row>
                    {/each}
                  </Table.Body>
                </Table.Root>
              </div>
            {:else}
              <p class="rounded-md border p-4 text-sm text-muted-foreground">No aptitude-area scores are available for this result.</p>
            {/if}
          </section>

          <p class="flex items-start gap-2 border-t pt-4 text-xs leading-relaxed text-muted-foreground">
            <LockKeyhole class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            This official result is available only to the applicant account linked to this application. The PDF is delivered privately and is not stored as a public download.
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'held_f2f'}
      <Card.Root class="border-amber-500/30 shadow-sm">
        <Card.Content class="space-y-4 p-6">
          <div class="space-y-1">
            <Card.Title class="text-xl">Result Prepared for Release Day</Card.Title>
            <Card.Description class="">
              Your result has been prepared for your Release Day. It will unlock here for online viewing and download after your Release Day appointment and Guidance encounter are completed.
            </Card.Description>
          </div>
          <Link href="/applicant/release-schedule" class="inline-flex min-h-11 items-center justify-center rounded-md border px-4 py-2 text-sm font-medium hover:bg-accent">
            View Release Day appointment
          </Link>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'preparing_pdf'}
      <Card.Root class="border-primary/20 shadow-sm">
        <Card.Content class="space-y-2 p-6">
          <Card.Title role="heading" aria-level="2" class="text-xl">Preparing your official PDF</Card.Title>
          <Card.Description class="">Your Release Day encounter is complete. Your official result PDF is being prepared for your Applicant Portal. Refresh this page shortly to check availability.</Card.Description>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'unavailable'}
      <Card.Root class="border-destructive/20 shadow-sm">
        <Card.Content class="space-y-2 p-6">
          <Card.Title role="heading" aria-level="2" class="text-xl">Result PDF unavailable</Card.Title>
          <Card.Description class="">The current official result PDF is not available for portal access. Contact Guidance to confirm your result document.</Card.Description>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'not_ready'}
      <Card.Root class="">
        <Card.Content class="space-y-2 p-6">
          <Card.Title class="text-xl">Scoring in Progress</Card.Title>
          <Card.Description class="">Your examination results have not been released yet.</Card.Description>
        </Card.Content>
      </Card.Root>
    {:else}
      <Card.Root class="">
        <Card.Content class="space-y-2 p-6">
          <Card.Title class="text-xl">Results unavailable</Card.Title>
          <Card.Description class="">No applicant examination record is linked to this account.</Card.Description>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>
</ApplicantLayout>
