<script>
  import { CheckCircle2, Clock, ShieldCheck } from 'lucide-svelte';
</script>

<section class="bg-muted/40 border-y border-border">
  <div class="mx-auto max-w-6xl px-4 py-16 space-y-10">
    <div class="mx-auto max-w-2xl space-y-4 text-center">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary sm:text-sm">
        Why SecureCAT
      </p>
      <h2 class="text-2xl font-bold tracking-tight sm:text-3xl md:text-4xl">
        End-to-end admission and testing in one place
      </h2>
      <p class="text-sm text-muted-foreground sm:text-base md:text-lg">
        From online application to examination, grading, and post‑exam consultation, SecureCAT keeps
        everything in a single, auditable workflow for both applicants and staff.
      </p>
    </div>

    <div class="grid max-w-4xl gap-4 mx-auto sm:grid-cols-3">
      <div class="flex flex-col gap-3 rounded-xl border border-border/70 bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <CheckCircle2 class="h-5 w-5" />
        </div>
        <div class="space-y-1">
          <h3 class="text-sm font-semibold sm:text-base md:text-lg">Streamlined applications</h3>
          <p class="text-xs text-muted-foreground sm:text-sm">
            Applicants apply once and track their status, while staff manage intakes, exam seasons,
            and decisions without spreadsheets.
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-3 rounded-xl border border-border/70 bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <Clock class="h-5 w-5" />
        </div>
        <div class="space-y-1">
          <h3 class="text-sm font-semibold sm:text-base md:text-lg">Faster exam operations</h3>
          <p class="text-xs text-muted-foreground sm:text-sm">
            Room assignments, proctor dashboards, grading sessions, and result printing are built‑in
            so exam days run on schedule.
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-3 rounded-xl border border-border/70 bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <ShieldCheck class="h-5 w-5" />
        </div>
        <div class="space-y-1">
          <h3 class="text-sm font-semibold sm:text-base md:text-lg">Secure & auditable</h3>
          <p class="text-xs text-muted-foreground sm:text-sm">
            Role‑based access, audit logs, and structured workflows help protect sensitive admission
            data and support compliance reviews.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

