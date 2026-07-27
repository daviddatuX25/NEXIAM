<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { useForm, usePage } from '@inertiajs/svelte';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import Switch from '@/Components/ui/switch/switch.svelte';
  import { Button } from '@/Components/ui/button';
  import { Textarea } from '@/Components/ui/textarea';
  import { Bot, Bell, Share2, FileCheck, Calculator, Ticket, FileText } from 'lucide-svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { GuidePanel, GuideSection, CopyableGroup } from '@/Components/Guide';

  let { ai_exam_companion_enabled = false, notify_on_publish = false, release_mode = 'online', allow_direct_assessment = true, allow_flexible_applications = false, enable_normalized_scores = false, admission_slip_enabled = false, admission_slip_html_template = '' } = $props();

  const form = useForm({
    ai_exam_companion_enabled,
    notify_on_publish,
    release_mode,
    allow_direct_assessment,
    allow_flexible_applications,
    enable_normalized_scores,
    admission_slip_enabled,
    admission_slip_html_template,
  });

  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Settings' }];

  let saveTimeout = $state(null);
  let templateSaveTimeout = $state(null);

  const page = usePage();
  const csrfToken = $derived($page.props.csrf_token ?? '');

  function autoSave() {
    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
      $form.put('/admin/settings', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {},
        onError: (errors) => {
          const first = Object.values(errors)[0];
          showError(first ?? 'Failed to save settings.');
          if (errors.enable_normalized_scores) {
            $form.enable_normalized_scores = enable_normalized_scores;
          }
        },
      });
    }, 300);
  }

  function toggleField(field, value) {
    form.update((f) => ({ ...f, [field]: value }));
    autoSave();
  }

  function saveTemplate() {
    $form.put('/admin/settings', {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => showSuccess('Admission slip template saved.'),
      onError: (errors) => {
        const first = Object.values(errors)[0];
        showError(first ?? 'Failed to save template.');
      },
    });
  }

  let previewHtml = $state('');
  let previewLoading = $state(false);
  let previewError = $state(null);
  let previewDebounce = null;

  function fetchPreview() {
    clearTimeout(previewDebounce);
    previewDebounce = setTimeout(() => {
      if (!$form.admission_slip_html_template?.trim()) {
        previewHtml = '';
        previewLoading = false;
        return;
      }
      previewLoading = true;
      previewError = null;
      fetch('/admin/settings/admission-slip-preview', {
        method: 'POST',
        body: JSON.stringify({ content: $form.admission_slip_html_template }),
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
      })
        .then(async (r) => {
          if (!r.ok) {
            const text = await r.text();
            let msg = 'Preview failed';
            try { msg = JSON.parse(text)?.error ?? JSON.parse(text)?.message ?? msg; } catch {}
            throw new Error(msg);
          }
          return r.json();
        })
        .then((data) => {
          previewHtml = data.html || '';
          previewError = null;
        })
        .catch((err) => {
          previewError = err.message || 'Preview failed';
          previewHtml = '';
        })
        .finally(() => (previewLoading = false));
    }, 400);
  }

  $effect(() => {
    $form.admission_slip_html_template;
    if ($form.admission_slip_enabled && $form.admission_slip_html_template) fetchPreview();
    else previewHtml = '';
  });

  const applicantPlaceholders = [
    { value: '{{applicant_number}}', label: 'Applicant number' },
    { value: '{{applicant_no}}', label: 'Applicant number (alias)' },
    { value: '{{reference_number}}', label: 'Application reference number (Deprecated: use applicant_number)' },
    { value: '{{full_name}}', label: 'Full name (computed)' },
    { value: '{{first_name}}', label: 'First name' },
    { value: '{{last_name}}', label: 'Last name' },
    { value: '{{middle_name}}', label: 'Middle name' },
    { value: '{{suffix}}', label: 'Suffix (Jr., Sr., etc.)' },
    { value: '{{birthdate}}', label: 'Birthdate (formatted)' },
    { value: '{{sex}}', label: 'Sex (capitalized)' },
    { value: '{{course_1}}', label: '1st course preference' },
    { value: '{{course_2}}', label: '2nd course preference' },
    { value: '{{course_3}}', label: '3rd course preference' },
    { value: '{{qr_code}}', label: 'QR code image (encodes applicant number)' },
  ];

  const institutionPlaceholders = [
    { value: '{{institution_name}}', label: 'Institution name' },
    { value: '{{institution_address}}', label: 'Institution address' },
    { value: '{{institution_logo}}', label: 'Institution logo <img> tag' },
    { value: '{{exam_title}}', label: 'Exam title' },
    { value: '{{academic_year}}', label: 'Active academic year label' },
    { value: '{{registrar_name}}', label: 'Registrar name' },
  ];

  const dualSlotPlaceholders = [
    { value: '{{applicant_number_2}}', label: 'Applicant 2 applicant number' },
    { value: '{{applicant_no_2}}', label: 'Applicant 2 applicant number (alias)' },
    { value: '{{reference_number_2}}', label: 'Applicant 2 reference number (Deprecated)' },
    { value: '{{full_name_2}}', label: 'Applicant 2 full name' },
    { value: '{{first_name_2}}', label: 'Applicant 2 first name' },
    { value: '{{last_name_2}}', label: 'Applicant 2 last name' },
    { value: '{{middle_name_2}}', label: 'Applicant 2 middle name' },
    { value: '{{suffix_2}}', label: 'Applicant 2 suffix' },
    { value: '{{birthdate_2}}', label: 'Applicant 2 birthdate' },
    { value: '{{sex_2}}', label: 'Applicant 2 sex' },
    { value: '{{course_1_2}}', label: 'Applicant 2 1st course' },
    { value: '{{course_2_2}}', label: 'Applicant 2 2nd course' },
    { value: '{{course_3_2}}', label: 'Applicant 2 3rd course' },
    { value: '{{qr_code_2}}', label: 'Applicant 2 QR code' },
  ];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <p class="mt-1 text-sm text-muted-foreground">System-wide feature toggles and configuration.</p>
      {#if $form.processing}
        <span class="text-sm text-muted-foreground animate-pulse">Saving...</span>
      {/if}
    </div>

    <div class="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Bot class="h-5 w-5" />
            AI exam companion
          </CardTitle>
          <CardDescription>
            When enabled, applicants whose consultation has been released can use the chat advisor. Advice is grounded in their scores and institutional knowledge you upload.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.ai_exam_companion_enabled}
            onCheckedChange={(checked) => toggleField('ai_exam_companion_enabled', checked)}
            aria-label="Enable AI exam companion"
          />
          <span class="text-sm font-medium">
            {$form.ai_exam_companion_enabled ? 'Enabled' : 'Disabled'}
          </span>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Bell class="h-5 w-5" />
            Exam schedule notifications
          </CardTitle>
          <CardDescription>
            When enabled, applicants receive an email when their exam session is published.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.notify_on_publish}
            onCheckedChange={(checked) => toggleField('notify_on_publish', checked)}
            aria-label="Enable exam schedule notifications"
          />
          <span class="text-sm font-medium">
            {$form.notify_on_publish ? 'Enabled' : 'Disabled'}
          </span>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Share2 class="h-5 w-5" />
            Result release mode
          </CardTitle>
          <CardDescription>
            Controls how exam results are delivered to applicants. At least one mode must be enabled.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div
            class="inline-flex rounded-lg border border-border p-1 gap-1 cursor-pointer select-none"
            role="radiogroup"
            aria-label="Release mode"
          >
            <button
              type="button"
              role="radio"
              aria-checked={$form.release_mode === 'online'}
              class="px-4 py-2 rounded-md text-sm font-medium transition-colors {$form.release_mode === 'online' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'}"
              onclick={() => toggleField('release_mode', 'online')}
            >
              Online
            </button>
            <button
              type="button"
              role="radio"
              aria-checked={$form.release_mode === 'f2f'}
              class="px-4 py-2 rounded-md text-sm font-medium transition-colors {$form.release_mode === 'f2f' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'}"
              onclick={() => toggleField('release_mode', 'f2f')}
            >
              F2F
            </button>
          </div>
          <p class="mt-3 text-xs text-muted-foreground">
            {#if $form.release_mode === 'online'}
              Results visible in portal with email delivery
            {:else}
              Results handed in person — portal view disabled for applicants
            {/if}
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileCheck class="h-5 w-5" />
            Direct Assessment
          </CardTitle>
          <CardDescription>
            When enabled, staff can create direct assessment sessions to encode scores immediately without scheduling a physical exam session. Useful for walk-in applicants or offline score entry.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.allow_direct_assessment}
            onCheckedChange={(checked) => toggleField('allow_direct_assessment', checked)}
            aria-label="Enable direct assessment"
          />
          <span class="text-sm font-medium">
            {$form.allow_direct_assessment ? 'Enabled' : 'Disabled'}
          </span>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileText class="h-5 w-5" />
            Flexible Application Details
          </CardTitle>
          <CardDescription>
            When enabled, applicants can submit applications without providing email, birthdate, sex, classification, or course preferences. Authenticated staff/admins can always bypass these requirements regardless of this toggle.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.allow_flexible_applications}
            onCheckedChange={(checked) => toggleField('allow_flexible_applications', checked)}
            aria-label="Enable flexible application details"
          />
          <span class="text-sm font-medium">
            {$form.allow_flexible_applications ? 'Enabled' : 'Disabled'}
          </span>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Calculator class="h-5 w-5" />
            Score Entry & Auto-Computation
          </CardTitle>
          <CardDescription>
            Choose how exam scores are entered and processed across the system (affects both manual entry and bulk imports).
            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-2 border-t border-border/50">
              <div>
                <span class="font-medium text-foreground">Enabled (Auto-Compute):</span>
                <p class="text-muted-foreground mt-0.5">Input raw scores (items correct). The system automatically computes normalized scores using formulas or looks up percentile grades via conversion tables.</p>
              </div>
              <div>
                <span class="font-medium text-foreground">Disabled (Manual Mode):</span>
                <p class="text-muted-foreground mt-0.5">Input pre-converted scores directly. Enter the final normalized score (e.g. 85.50) or percentile string (e.g. "95th") manually.</p>
              </div>
            </div>
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.enable_normalized_scores}
            onCheckedChange={(checked) => toggleField('enable_normalized_scores', checked)}
            aria-label="Enable automated score computation"
          />
          <span class="text-sm font-medium">
            {$form.enable_normalized_scores ? 'Auto-Compute Enabled' : 'Manual Mode Enabled'}
          </span>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Ticket class="h-5 w-5" />
            Admission Slip Distribution
          </CardTitle>
          <CardDescription>
            When enabled, accepted applicants can download and print admission slips from their portal, and staff can bulk-print slips from the admin panel.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex items-center gap-4">
          <Switch
            checked={$form.admission_slip_enabled}
            onCheckedChange={(checked) => toggleField('admission_slip_enabled', checked)}
            aria-label="Enable admission slip distribution"
          />
          <span class="text-sm font-medium">
            {$form.admission_slip_enabled ? 'Enabled' : 'Disabled'}
          </span>
        </CardContent>
      </Card>

      {#if $form.admission_slip_enabled}
        <Card>
          <CardHeader>
            <CardTitle>Admission Slip HTML Template</CardTitle>
            <CardDescription>
              Custom HTML template for admission slips. Leave blank to use the default built-in template. Uses <code class="text-xs bg-muted px-1 py-0.5 rounded">{{placeholder}}</code> syntax for dynamic content.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <GuidePanel title="Placeholder Reference">
              <GuideSection title="Applicant">
                <CopyableGroup items={applicantPlaceholders} subtitle="Identity & Course Preferences" />
              </GuideSection>
              <GuideSection title="Institution">
                <CopyableGroup items={institutionPlaceholders} />
              </GuideSection>
              <GuideSection title="Dual-Slot (Half-page Layouts)" visible={true}>
                <CopyableGroup items={dualSlotPlaceholders} subtitle="Applicant 2 — for half-page layouts printing 2 per page" />
              </GuideSection>
            </GuidePanel>

            <div>
              <label for="admission_slip_html_template" class="text-sm font-medium">HTML + CSS (JavaScript not allowed)</label>
              <p class="text-xs text-muted-foreground mt-0.5">Scripts and event handlers are stripped for security. Leave empty to use the default template.</p>
              <Textarea
                id="admission_slip_html_template"
                bind:value={$form.admission_slip_html_template}
                rows="16"
                placeholder="<div style=&quot;padding: 2rem; font-family: sans-serif;&quot;>&#10;  <h1>{{institution_name}}</h1>&#10;  <p>Applicant No.: <strong>{{applicant_number}}</strong></p>&#10;  <p>Name: {{full_name}}</p>&#10;</div>"
                class="mt-2 flex w-full font-mono"
              />
              {#if $form.errors?.admission_slip_html_template}<p class="text-sm text-destructive mt-1">{$form.errors.admission_slip_html_template}</p>{/if}
            </div>

            <div class="flex items-center gap-3">
              <Button onclick={saveTemplate} disabled={$form.processing}>
                {$form.processing ? 'Saving...' : 'Save Template'}
              </Button>
              <Button variant="outline" onclick={() => { $form.admission_slip_html_template = ''; saveTemplate(); }}>
                Reset to Default
              </Button>
            </div>

            {#if $form.admission_slip_html_template}
              <div class="rounded-lg border bg-card p-4">
                <h3 class="text-sm font-medium mb-2">Live preview</h3>
                {#if previewLoading}
                  <p class="text-sm text-muted-foreground p-4">Loading...</p>
                {:else if previewError}
                  <p class="text-sm text-destructive p-4">{previewError}</p>
                {:else if previewHtml}
                  <iframe
                    srcdoc={previewHtml}
                    class="w-full rounded border bg-white"
                    style="min-height: 400px; max-height: 600px;"
                    sandbox="allow-same-origin"
                    title="Admission Slip Preview"
                    onload={(e) => {
                      const h = e.target.contentDocument?.body?.scrollHeight;
                      if (h) e.target.style.height = `${h + 32}px`;
                    }}
                  ></iframe>
                {:else}
                  <p class="text-sm text-muted-foreground p-4">Type template content to see preview.</p>
                {/if}
              </div>
            {/if}
          </CardContent>
        </Card>
      {/if}
    </div>
  </div>
</AuthenticatedLayout>