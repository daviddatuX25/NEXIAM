<script>
    import MediaFrame from '@/Components/public/MediaFrame.svelte';
    import { reveal } from '@/lib/motion.js';

    let {
        story = {},
        preview = false,
        eyebrow = null,
    } = $props();

    const copyFirst = $derived((story.align ?? 'right') === 'right');
</script>

<section
    class={copyFirst
        ? 'grid items-center gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16'
        : 'grid items-center gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16'}
    use:reveal={{ y: 24, duration: 0.72 }}
>
    <div class={copyFirst ? 'lg:order-2' : ''}>
        <MediaFrame image={story.image} ratio="aspect-[5/4]" showSource={preview} />
    </div>

    <div class={`max-w-xl ${copyFirst ? 'lg:order-1' : ''}`}>
        <p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">
            {eyebrow ?? story.eyebrow}
        </p>
        <h2 class="text-foreground mt-4 text-3xl font-semibold leading-[1.05] tracking-[-0.035em] text-balance sm:text-4xl">
            {story.title}
        </h2>
        <p class="text-muted-foreground mt-5 max-w-[55ch] text-base leading-8 sm:text-lg">
            {story.body}
        </p>
        <div class="mt-7 h-px w-16 bg-primary/50" aria-hidden="true"></div>
    </div>
</section>
