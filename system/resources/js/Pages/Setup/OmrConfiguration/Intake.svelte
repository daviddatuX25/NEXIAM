<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Link, usePage } from '@inertiajs/svelte';

  const page = usePage();
  const saveError = $derived(page.props.errors?.omr_bundle ?? '');
  const status = $derived(page.props.flash?.status ?? '');

  let templateCode = $state('');
  let templateFile = $state(null);
  let configFile = $state(null);
  let manifestFile = $state(null);
  let referenceFiles = $state([]);
  let saving = $state(false);

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Register bundle' },
  ];
</script>

<StaffLayout>
  <PageHeaderBar title="Register native bundle" description="Upload a verified OMRChecker-native bundle (template.json + config.json + reference assets + manifest.json)." {breadcrumbs} />

  <section class="max-w-2xl space-y-6 p-6">
    {#if status}<p class="rounded bg-green-50 p-3 text-sm text-green-800">{status}</p>{/if}
    {#if saveError}<p class="rounded bg-red-50 p-3 text-sm text-red-800">{saveError}</p>{/if}

    <form method="post" action="/setup/omr/bundles" enctype="multipart/form-data" class="space-y-5">
      <div class="space-y-2">
        <Label for="template_code">Template code</Label>
        <Input id="template_code" name="template_code" placeholder="e.g. REAL-OMR-2026" bind:value={templateCode} required />
      </div>

      <div class="space-y-2">
        <Label for="template">template.json</Label>
        <Input id="template" name="template" type="file" accept=".json" bind:files={templateFile} required />
      </div>

      <div class="space-y-2">
        <Label for="config">config.json</Label>
        <Input id="config" name="config" type="file" accept=".json" bind:files={configFile} required />
      </div>

      <div class="space-y-2">
        <Label for="manifest">manifest.json</Label>
        <Input id="manifest" name="manifest" type="file" accept=".json" bind:files={manifestFile} required />
      </div>

      <div class="space-y-2">
        <Label for="references">Reference assets (one SVG per page)</Label>
        <Input id="references" name="references[]" type="file" accept=".svg" multiple bind:files={referenceFiles} required />
      </div>

      <div class="flex gap-3">
        <Button type="submit" disabled={saving}>{saving ? 'Registering…' : 'Register bundle'}</Button>
        <Button asChild variant="outline">
          <Link href="/setup/omr">Cancel</Link>
        </Button>
      </div>
    </form>
  </section>
</StaffLayout>
