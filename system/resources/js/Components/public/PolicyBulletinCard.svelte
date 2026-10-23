<script>
    let { policy } = $props();
</script>

<article
    class="group relative flex min-w-0 flex-col justify-between rounded-xl border border-amber-900/10 dark:border-border/80 bg-[#FDFBF7] dark:bg-card/90 p-5 pt-6 shadow-[0_8px_20px_-4px_rgba(0,0,0,0.08),0_3px_6px_-2px_rgba(0,0,0,0.04)] transition-all duration-200 hover:-translate-y-1 hover:rotate-[-0.5deg] hover:shadow-[0_14px_28px_-6px_rgba(0,0,0,0.12),0_6px_12px_-3px_rgba(0,0,0,0.06)]"
>
    <div
        class="absolute -top-3 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center pointer-events-none"
        aria-hidden="true"
    >
        <div class="size-4 rounded-full bg-gradient-to-br from-rose-400 via-rose-600 to-rose-900 ring-1 ring-white/60 shadow-[0_2px_4px_rgba(0,0,0,0.35)]"></div>
        <div class="w-1.5 h-1 bg-black/25 rounded-full blur-[1px] mt-0.5"></div>
    </div>

    <div class="space-y-2">
        <div
            class="flex items-center gap-2 border-b border-amber-900/10 dark:border-border/50 pb-2 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider"
        >
            <span>{policy.publisher_label ?? 'Institutional Policy'}</span>
        </div>
        <h3 class="text-base font-bold tracking-tight text-foreground line-clamp-2">
            {policy.title}
        </h3>
        <p class="text-xs text-muted-foreground leading-relaxed line-clamp-3">
            {policy.excerpt}
        </p>
    </div>

    <div class="mt-4 border-t border-amber-900/10 dark:border-border/50 pt-3 flex items-center justify-between gap-2 text-xs">
        <span class="text-[11px] text-muted-foreground">
            {policy.published_at ? new Date(policy.published_at).toLocaleDateString() : ''}
        </span>
        <a
            href={policy.href}
            class="text-primary font-semibold text-xs hover:underline underline-offset-4 inline-flex items-center gap-1"
            aria-label={`Read ${policy.title}`}
        >
            Read policy →
        </a>
    </div>
</article>
