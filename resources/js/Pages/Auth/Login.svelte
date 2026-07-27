<script>
  import HomeLayout from '@/Layouts/HomeLayout.svelte';
  import { onMount } from 'svelte';
  import { useForm, usePage, Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Card from '@/Components/ui/card';
  import { Eye, EyeOff } from 'lucide-svelte';

  let activeTab = $state('applicant'); // 'applicant' or 'staff'
  let showApplicantPassword = $state(false);
  let showStaffPassword = $state(false);

  onMount(() => {
    const saved = localStorage.getItem('loginTab');
    if (saved === 'applicant' || saved === 'staff') {
      activeTab = saved;
    }
  });

  // Applicant form pointing to portal
  const applicantForm = useForm({
    email: '',
    password: '',
    remember: false,
  });

  // Staff form pointing to default staff auth
  const staffForm = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const page = usePage();
  const errors = $derived({ 
    ...($page.props.errors ?? {}), 
    ...(activeTab === 'applicant' ? $applicantForm.errors : $staffForm.errors ?? {}) 
  });
  const googleOAuthEnabled = $derived($page.props.googleOAuthEnabled ?? false);

  function handleSubmitApplicant(e) {
    e.preventDefault();
    $applicantForm.post('/portal/login');
  }

  function handleSubmitStaff(e) {
    e.preventDefault();
    $staffForm.post('/login');
  }
</script>

<svelte:head>
  <title>Login - SecureCAT</title>
</svelte:head>

<HomeLayout>
  <div class="flex items-center justify-center min-h-[calc(100vh-8rem)] py-12 px-4 sm:px-6 lg:px-8 bg-background relative overflow-hidden">
    <!-- Decorative backdrop for the elegant login -->
    <div class="absolute -top-[10%] -right-[10%] h-[400px] w-[400px] rounded-full bg-gradient-to-tr from-primary/10 to-blue-500/10 blur-3xl animate-in fade-in duration-1000" aria-hidden="true"></div>
    <div class="absolute bottom-[10%] -left-[10%] h-[500px] w-[500px] rounded-full bg-gradient-to-bl from-emerald-500/10 to-primary/10 blur-3xl animate-in fade-in duration-1000 delay-300" aria-hidden="true"></div>
    
    <div class="w-full max-w-md relative z-10 animate-in fade-in slide-in-from-bottom-4 duration-700">
      
      <!-- Prominent Tab Switcher -->
      <div class="bg-muted/50 p-1.5 rounded-2xl mb-6 flex items-center shadow-inner border border-border/50 relative">
        <!-- Active Tab Background Indicator -->
        <div 
          class="absolute top-1.5 left-1.5 bottom-1.5 w-[calc(50%-6px)] bg-background rounded-xl shadow-sm border border-border/60 transition-transform duration-300 ease-in-out"
          style="transform: translateX({activeTab === 'applicant' ? '0' : '100%'});"
        ></div>
        
        <button
          type="button"
          class="flex-1 py-3 text-sm font-bold relative z-10 transition-colors flex items-center justify-center gap-2 {activeTab === 'applicant' ? 'text-primary' : 'text-muted-foreground hover:text-foreground'}"
          onclick={() => { activeTab = 'applicant'; localStorage.setItem('loginTab', 'applicant'); }}
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z M12 14v7M5 10v7a7 7 0 0014 0v-7" />
          </svg>
          Applicant Portal
        </button>
        
        <button
          type="button"
          class="flex-1 py-3 text-sm font-bold relative z-10 transition-colors flex items-center justify-center gap-2 {activeTab === 'staff' ? 'text-primary' : 'text-muted-foreground hover:text-foreground'}"
          onclick={() => { activeTab = 'staff'; localStorage.setItem('loginTab', 'staff'); }}
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          Admin Portal
        </button>
      </div>

      <Card.Root class="shadow-2xl shadow-primary/5 bg-card/80 backdrop-blur-xl border-border/60 rounded-3xl overflow-hidden">
        
        <Card.Header class="pt-8 pb-4 text-center">
          <div class="h-16 w-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 text-primary flex items-center justify-center border border-primary/10 shadow-sm animate-pulse">
            {#if activeTab === 'applicant'}
              <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            {:else}
              <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            {/if}
          </div>
          <Card.Title class="text-3xl font-extrabold tracking-tight text-foreground">
            {activeTab === 'applicant' ? 'Applicant Login' : 'Admin Login'}
          </Card.Title>
          <Card.Description class="text-balance mt-3 text-base font-medium">
            {activeTab === 'applicant' 
              ? 'Sign in to your dashboard to check admission status, view exam scores, and schedule consultations.' 
              : 'Securely access the system to manage exams, applicants, and administrative settings.'}
          </Card.Description>
        </Card.Header>
        
        <Card.Content class="space-y-4 pb-8 px-8">
          <!-- APPLICANT FORM -->
          {#if activeTab === 'applicant'}
            <form onsubmit={handleSubmitApplicant} class="space-y-6 animate-in fade-in duration-300">
              <div class="space-y-2">
                <label for="app_email" class="text-sm font-bold text-foreground">Email Address</label>
                <Input
                  id="app_email"
                  type="email"
                  bind:value={$applicantForm.email}
                  placeholder="applicant@example.com"
                  autocomplete="email"
                  required
                  aria-invalid={!!errors?.email}
                  class="min-h-[48px] shadow-sm bg-background/50 focus:bg-background rounded-xl border-border/60"
                />
                {#if errors?.email}
                  <p class="text-sm text-destructive font-bold">{typeof errors.email === 'string' ? errors.email : errors.email[0]}</p>
                {/if}
              </div>

              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label for="app_password" class="text-sm font-bold text-foreground">Password</label>
                  <Link href="/portal/forgot-password" class="text-xs font-bold text-primary hover:underline hover:text-primary/80 transition-colors">Forgot password?</Link>
                </div>
                <div class="relative">
                  <Input
                    id="app_password"
                    type={showApplicantPassword ? 'text' : 'password'}
                    bind:value={$applicantForm.password}
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                    aria-invalid={!!errors?.password}
                    class="min-h-[48px] shadow-sm bg-background/50 focus:bg-background rounded-xl border-border/60 pr-10"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground"
                    onclick={() => (showApplicantPassword = !showApplicantPassword)}
                    aria-label={showApplicantPassword ? 'Hide password' : 'Show password'}
                  >
                    {#if showApplicantPassword}
                      <EyeOff class="size-4" />
                    {:else}
                      <Eye class="size-4" />
                    {/if}
                  </Button>
                </div>
                {#if errors?.password}
                  <p class="text-sm text-destructive font-bold">{typeof errors.password === 'string' ? errors.password : errors.password[0]}</p>
                {/if}
              </div>

              <div class="flex items-center gap-3 pt-1">
                <label class="inline-flex items-center gap-3 text-sm text-muted-foreground font-semibold cursor-pointer group">
                  <input
                    type="checkbox"
                    checked={$applicantForm.remember}
                    onchange={(e) => applicantForm.setData('remember', e.currentTarget.checked)}
                    class="h-5 w-5 rounded border-muted-foreground/30 text-primary shadow-sm focus:ring-primary focus:ring-offset-background transition-all"
                  />
                  <span class="group-hover:text-foreground transition-colors">Keep me signed in</span>
                </label>
              </div>

              <Button type="submit" class="w-full h-14 text-lg font-bold shadow-xl shadow-primary/20 hover:shadow-2xl hover:-translate-y-0.5 transition-all rounded-xl mt-2 {!$applicantForm.processing ? 'bg-gradient-to-r from-primary to-blue-600 hover:from-primary/90 hover:to-blue-600/90 border-0' : ''}" disabled={$applicantForm.processing}>
                {$applicantForm.processing ? 'Authenticating…' : 'Access Applicant Portal'}
              </Button>
            </form>
          {/if}

          <!-- STAFF FORM -->
          {#if activeTab === 'staff'}
            <form onsubmit={handleSubmitStaff} class="space-y-6 animate-in fade-in duration-300">
              <div class="space-y-2">
                <label for="staff_email" class="text-sm font-bold text-foreground">Email Address</label>
                <Input
                  id="staff_email"
                  type="email"
                  bind:value={$staffForm.email}
                  placeholder="admin@example.com"
                  autocomplete="email"
                  required
                  aria-invalid={!!errors?.email}
                  class="min-h-[48px] shadow-sm bg-background/50 focus:bg-background rounded-xl border-border/60"
                />
                {#if errors?.email}
                  <p class="text-sm text-destructive font-bold">{typeof errors.email === 'string' ? errors.email : errors.email[0]}</p>
                {/if}
              </div>

              <div class="space-y-2">
                <label for="staff_password" class="text-sm font-bold text-foreground">Password</label>
                <div class="relative">
                  <Input
                    id="staff_password"
                    type={showStaffPassword ? 'text' : 'password'}
                    bind:value={$staffForm.password}
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                    aria-invalid={!!errors?.password}
                    class="min-h-[48px] shadow-sm bg-background/50 focus:bg-background rounded-xl border-border/60 pr-10"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground"
                    onclick={() => (showStaffPassword = !showStaffPassword)}
                    aria-label={showStaffPassword ? 'Hide password' : 'Show password'}
                  >
                    {#if showStaffPassword}
                      <EyeOff class="size-4" />
                    {:else}
                      <Eye class="size-4" />
                    {/if}
                  </Button>
                </div>
                {#if errors?.password}
                  <p class="text-sm text-destructive font-bold">{typeof errors.password === 'string' ? errors.password : errors.password[0]}</p>
                {/if}
              </div>

              <div class="flex items-center gap-3 pt-1">
                <label class="inline-flex items-center gap-3 text-sm text-muted-foreground font-semibold cursor-pointer group">
                  <input
                    type="checkbox"
                    checked={$staffForm.remember}
                    onchange={(e) => staffForm.setData('remember', e.currentTarget.checked)}
                    class="h-5 w-5 rounded border-muted-foreground/30 text-primary shadow-sm focus:ring-primary focus:ring-offset-background transition-all"
                  />
                  <span class="group-hover:text-foreground transition-colors">Remember my computer</span>
                </label>
              </div>

              {#if errors?.google}
                <p class="text-sm text-destructive font-bold">{errors.google}</p>
              {/if}

              {#if googleOAuthEnabled}
                <div class="relative">
                  <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t border-border/60"></span>
                  </div>
                  <div class="relative flex justify-center text-xs">
                    <span class="bg-card px-3 text-muted-foreground font-semibold uppercase tracking-wide">or</span>
                  </div>
                </div>

                <a
                  href="/auth/google"
                  class="flex items-center justify-center gap-3 w-full h-12 rounded-xl border border-border/60 bg-background hover:bg-muted transition-colors text-sm font-bold shadow-sm"
                >
                  <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                  </svg>
                  Sign in with Google
                </a>
              {/if}

              <Button variant="secondary" type="submit" class="w-full h-14 text-lg font-bold shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all rounded-xl mt-2 border border-border/50" disabled={$staffForm.processing}>
                {$staffForm.processing ? 'Authenticating…' : 'Sign in as Admin'}
              </Button>
            </form>
          {/if}

        </Card.Content>
      </Card.Root>
      
      {#if activeTab === 'applicant'}
        <div class="text-center mt-8 animate-in fade-in duration-500 delay-200">
          <p class="text-base text-muted-foreground font-medium">
            Don't have an application account yet?
          </p>
          <Link href="/apply" class="mt-2 inline-flex items-center justify-center h-12 px-6 rounded-full bg-card border border-border/60 text-foreground font-bold shadow-sm hover:bg-muted transition-colors group">
            Start Your Application Now
            <svg class="ml-2 h-4 w-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </Link>
        </div>
      {/if}
    </div>
  </div>
</HomeLayout>
