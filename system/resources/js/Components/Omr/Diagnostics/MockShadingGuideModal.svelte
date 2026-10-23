<script>
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogHeader,
        DialogTitle,
    } from '@/Components/ui/dialog';
    import OmrShadingGuideViewer from './OmrShadingGuideViewer.svelte';

    let {
        open = $bindable(false),
        guide = null,
        instanceCount = 1,
        patternMode = guide?.pattern_mode ?? 'guided_mix',
        onClose = () => {},
    } = $props();

    function close(value) {
        open = value;
        if (!value) onClose();
    }
</script>

<Dialog {open} onOpenChange={close}>
    <DialogContent class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-y-auto p-4 sm:p-6">
        <DialogHeader>
            <DialogTitle>Mock Shading Guide</DialogTitle>
            <DialogDescription>Reference-only instructions for the pinned answer sheet. Intentional marks are practice data and are never sent to OMR recognition as expected answers.</DialogDescription>
        </DialogHeader>
        <OmrShadingGuideViewer
            pages={guide?.pages ?? []}
            sections={guide?.sections ?? []}
            instructions={guide?.instructions ?? []}
            {instanceCount}
            {patternMode}
            markingLegend={guide?.marking_legend ?? guide?.legend ?? [
                { intent: 'correct', label: 'Correct Set A key' },
                { intent: 'wrong', label: 'Intentional wrong mark' },
                { intent: 'double', label: 'Double-shaded · expect review' },
                { intent: 'blank', label: 'Leave blank' },
            ]}
            outcomeSummary={guide?.outcome_summary ?? null}
            title="Intentional Marks"
        />
    </DialogContent>
</Dialog>
