<script>
  import { Users, ClipboardList, Shield } from 'lucide-svelte';
</script>

<section class="border-t border-border/60 bg-background">
  <div class="mx-auto max-w-6xl px-4 py-16 space-y-8">
    <header class="max-w-3xl mx-auto text-center space-y-2">
      <p class="text-sm font-semibold uppercase tracking-wide text-primary">One system, three views</p>
      <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
        Designed for applicants, staff, and leadership
      </h2>
      <p class="text-sm text-muted-foreground sm:text-base">
        SecureCAT keeps the same source of truth but lets each role see what matters to them—from
        filling out an application to monitoring capacity and audit trails.
      </p>
    </header>

    <div class="grid gap-6 md:grid-cols-3">
      <article class="flex flex-col gap-3 rounded-lg border border-border bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <Users class="h-5 w-5" />
        </div>
        <h3 class="text-sm font-semibold sm:text-base">Applicant perspective</h3>
        <p class="text-xs text-muted-foreground sm:text-sm">
          A clear online form, email updates, and an applicant portal help students understand where
          they are in the process without repeated visits to campus.
        </p>
      </article>

      <article class="flex flex-col gap-3 rounded-lg border border-border bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <ClipboardList class="h-5 w-5" />
        </div>
        <h3 class="text-sm font-semibold sm:text-base">Admissions & testing staff</h3>
        <p class="text-xs text-muted-foreground sm:text-sm">
          Staff work from a single dashboard for seasons, applications, exam sessions, proctoring,
          grading, and result release—no scattered spreadsheets.
        </p>
      </article>

      <article class="flex flex-col gap-3 rounded-lg border border-border bg-card p-5 shadow-sm">
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <Shield class="h-5 w-5" />
        </div>
        <h3 class="text-sm font-semibold sm:text-base">Leadership & compliance</h3>
        <p class="text-xs text-muted-foreground sm:text-sm">
          Built‑in roles, logs, and structured workflows support security reviews and accreditation
          requirements while keeping sensitive data access tightly scoped.
        </p>
      </article>
    </div>
  </div>
</section>

