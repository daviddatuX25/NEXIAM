<script>
  let {
    avatar = { type: 'initials', preset: null, url: null },
    name = '',
    size = 'md',
    class: className = '',
  } = $props();

  const sizes = {
    xs: 'size-6 text-[10px]',
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-12 text-base',
    xl: 'size-16 text-xl',
    '2xl': 'size-24 text-3xl',
  };

  const presetTitles = {
    counselor: 'Guidance counselor',
    guidance_counselor: 'Guidance counselor',
    guidance_clerk: 'Guidance clerk',
    guidance_support: 'Guidance support staff',
    guidance_support_staff: 'Guidance support staff',
    registrar_admin: 'Registrar administrator',
    records: 'Registrar clerk',
    registrar_clerk: 'Registrar clerk',
    registrar_support: 'Registrar support staff',
    registrar_support_staff: 'Registrar support staff',
    program_head: 'Program Head',
    dean: 'Academic Dean',
    academic_exec: 'Academic Dean / Program Head',
    hr_officer: 'Human Resource officer',
    exam_proctor: 'Test Administrator',
  };

  const initials = $derived.by(() => {
    const words = name
      .trim()
      .split(/\s+/)
      .map((w) => w.replace(/[^A-Za-z0-9]/g, ''))
      .filter(Boolean);
    if (words.length === 0) return 'U';
    if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
    return `${words[0][0]}${words[words.length - 1][0]}`.toUpperCase();
  });
</script>

<span
  class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full {sizes[size] ?? sizes.md} {className}"
  aria-hidden="true"
>
  {#if avatar?.type === 'upload' && avatar?.url}
    <img src={avatar.url} alt="" class="size-full rounded-full object-cover shadow-inner" />
  {:else if avatar?.type === 'preset' && avatar?.preset && presetTitles[avatar.preset]}
    <svg
      viewBox="0 0 96 96"
      class="size-full rounded-full"
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient id="avatar-counselor-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#047857"/><stop offset="1" stop-color="#a7f3d0"/></linearGradient>
        <linearGradient id="avatar-guidance-clerk-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0f766e"/><stop offset="1" stop-color="#5eead4"/></linearGradient>
        <linearGradient id="avatar-guidance-support-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0284c7"/><stop offset="1" stop-color="#99f6e4"/></linearGradient>
        <linearGradient id="avatar-registrar-admin-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#881337"/><stop offset="1" stop-color="#f43f5e"/></linearGradient>
        <linearGradient id="avatar-records-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#b45309"/><stop offset="1" stop-color="#be123c"/></linearGradient>
        <linearGradient id="avatar-registrar-support-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#c2410c"/><stop offset="1" stop-color="#fde047"/></linearGradient>
        <linearGradient id="avatar-program-head-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#3730a3"/><stop offset="1" stop-color="#818cf8"/></linearGradient>
        <linearGradient id="avatar-dean-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#4c1d95"/><stop offset="1" stop-color="#c084fc"/></linearGradient>
        <linearGradient id="avatar-hr-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0e7490"/><stop offset="1" stop-color="#38bdf8"/></linearGradient>
        <linearGradient id="avatar-proctor-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#78350f"/><stop offset="1" stop-color="#fbbf24"/></linearGradient>
      </defs>
      {#if avatar.preset === 'counselor' || avatar.preset === 'guidance_counselor'}
        <rect width="96" height="96" fill="url(#avatar-counselor-gradient)"/>
        <circle cx="48" cy="39" r="20" fill="#f8d6b5"/>
        <path d="M28 39c0-18 10-25 21-25 13 0 21 10 19 25l-4-8c-12 2-20-2-27-7l-6 15Z" fill="#173f35"/>
        <path d="M33 37h12v9H33zm18 0h12v9H51zM45 41h6" fill="none" stroke="#173f35" stroke-width="3" stroke-linejoin="round"/>
        <path d="M22 96c2-19 12-29 26-29s24 10 26 29" fill="#064e3b"/>
        <path d="M48 78c-7-6-12-9-12-14a7 7 0 0 1 12-4 7 7 0 0 1 12 4c0 5-5 8-12 14Z" fill="#fb7185" stroke="#fff" stroke-width="2"/>
      {:else if avatar.preset === 'guidance_clerk'}
        <rect width="96" height="96" fill="url(#avatar-guidance-clerk-gradient)"/>
        <rect x="20" y="22" width="56" height="60" rx="6" fill="#f0fdfa" stroke="#134e4a" stroke-width="3"/>
        <path d="M38 16h20v10H38z" fill="#ccfbf1" stroke="#134e4a" stroke-width="3" stroke-linejoin="round"/>
        <circle cx="48" cy="21" r="3" fill="#134e4a"/>
        <path d="M30 36h36M30 46h24M30 56h36M30 66h20" stroke="#0f766e" stroke-width="3" stroke-linecap="round"/>
        <circle cx="66" cy="62" r="14" fill="#14b8a6" stroke="#134e4a" stroke-width="3"/>
        <path d="m61 62 4 4 8-8" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      {:else if avatar.preset === 'guidance_support' || avatar.preset === 'guidance_support_staff'}
        <rect width="96" height="96" fill="url(#avatar-guidance-support-gradient)"/>
        <rect x="22" y="24" width="52" height="56" rx="6" fill="#f0fdfa" stroke="#0f766e" stroke-width="3"/>
        <path d="M34 24v-6h28v6" fill="none" stroke="#0f766e" stroke-width="3" stroke-linecap="round"/>
        <path d="M32 40h32M32 50h22M32 60h16" stroke="#0d9488" stroke-width="3" stroke-linecap="round"/>
        <circle cx="64" cy="62" r="13" fill="#2dd4bf" stroke="#0f766e" stroke-width="3"/>
        <path d="m59 62 4 4 7-7" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      {:else if avatar.preset === 'registrar_admin'}
        <rect width="96" height="96" fill="url(#avatar-registrar-admin-gradient)"/>
        <path d="M14 42h68v42H14z" fill="#fff1f2" stroke="#4c0519" stroke-width="3"/>
        <path d="M10 42 48 18l38 24z" fill="#ffe4e6" stroke="#4c0519" stroke-width="3" stroke-linejoin="round"/>
        <path d="M26 52v24m22-24v24m22-24v24" stroke="#9f1239" stroke-width="5"/>
        <circle cx="48" cy="64" r="14" fill="#fbbf24" stroke="#78350f" stroke-width="3"/>
        <path d="m48 54 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z" fill="#b45309"/>
      {:else if avatar.preset === 'records' || avatar.preset === 'registrar_clerk'}
        <rect width="96" height="96" fill="url(#avatar-records-gradient)"/>
        <path d="M19 46h58v36H19z" fill="#fff7ed"/><path d="M19 46l8-10h21l7 10" fill="#fed7aa" stroke="#7c2d12" stroke-width="3" stroke-linejoin="round"/>
        <path d="M31 60h34M31 69h24" stroke="#9a3412" stroke-width="3" stroke-linecap="round"/>
        <circle cx="66" cy="30" r="16" fill="#fef3c7" stroke="#7c2d12" stroke-width="3"/>
        <path d="m66 18 3 7 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z" fill="#b45309"/>
      {:else if avatar.preset === 'registrar_support' || avatar.preset === 'registrar_support_staff'}
        <rect width="96" height="96" fill="url(#avatar-registrar-support-gradient)"/>
        <path d="M18 36h60v44H18z" fill="#fffbeb" stroke="#9a3412" stroke-width="3"/>
        <path d="M18 52h18l4 6h16l4-6h18" fill="none" stroke="#9a3412" stroke-width="3" stroke-linejoin="round"/>
        <path d="M28 26h40v14H28z" fill="#ffedd5" stroke="#ea580c" stroke-width="2"/>
        <path d="M36 33h24" stroke="#c2410c" stroke-width="3" stroke-linecap="round"/>
        <circle cx="64" cy="64" r="12" fill="#f97316" stroke="#7c2d12" stroke-width="2"/>
        <path d="m60 64 3 3 6-6" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      {:else if avatar.preset === 'program_head'}
        <rect width="96" height="96" fill="url(#avatar-program-head-gradient)"/>
        <path d="M20 30c14-5 28 0 28 0v42s-14-5-28 0zm56 0c-14-5-28 0-28 0v42s14-5 28 0z" fill="#e0e7ff" stroke="#312e81" stroke-width="3" stroke-linejoin="round"/>
        <path d="M48 30v42" stroke="#312e81" stroke-width="3"/>
        <path d="M28 42h12M28 52h12m16-10h12m-12 10h12" stroke="#6366f1" stroke-width="2" stroke-linecap="round"/>
        <circle cx="70" cy="24" r="13" fill="#fbbf24" stroke="#78350f" stroke-width="2"/>
        <path d="m70 17 2 4 5 1-4 3 1 5-4-2-4 2 1-5-4-3 5-1z" fill="#d97706"/>
      {:else if avatar.preset === 'dean' || avatar.preset === 'academic_exec'}
        <rect width="96" height="96" fill="url(#avatar-dean-gradient)"/>
        <path d="m14 32 34-15 34 15-34 15z" fill="#f5f3ff" stroke="#3b0764" stroke-width="3" stroke-linejoin="round"/>
        <path d="M28 39v11c9 9 31 9 40 0V39L48 48z" fill="#ede9fe" stroke="#3b0764" stroke-width="2"/>
        <path d="M80 33v19" stroke="#fbbf24" stroke-width="3"/><circle cx="80" cy="56" r="4" fill="#fbbf24"/>
        <circle cx="48" cy="67" r="16" fill="#fff" stroke="#581c87" stroke-width="2"/>
        <path d="M48 56l4 8 9 1-7 6 2 9-8-4-8 4 2-9-7-6 9-1z" fill="#a855f7"/>
        <path d="M25 96c2-12 10-18 23-18s21 6 23 18" fill="#581c87"/>
      {:else if avatar.preset === 'hr_officer'}
        <rect width="96" height="96" fill="url(#avatar-hr-gradient)"/>
        <rect x="22" y="24" width="52" height="60" rx="8" fill="#f0f9ff" stroke="#164e63" stroke-width="3"/>
        <rect x="36" y="16" width="24" height="12" rx="3" fill="#38bdf8" stroke="#164e63" stroke-width="2"/>
        <circle cx="48" cy="42" r="11" fill="#bae6fd" stroke="#0369a1" stroke-width="3"/>
        <path d="M32 68c2-8 9-11 16-11s14 3 16 11" fill="#0284c7"/>
        <path d="M30 76h36" stroke="#0891b2" stroke-width="3" stroke-linecap="round"/>
      {:else if avatar.preset === 'exam_proctor'}
        <rect width="96" height="96" fill="url(#avatar-proctor-gradient)"/>
        <circle cx="48" cy="37" r="23" fill="#fffbeb" stroke="#78350f" stroke-width="4"/><path d="M48 37 48 24m0 13 11 6" stroke="#92400e" stroke-width="4" stroke-linecap="round"/><circle cx="48" cy="37" r="3" fill="#92400e"/>
        <path d="M42 13h12m-6 0V9m17 5 5-5" stroke="#fff7ed" stroke-width="4" stroke-linecap="round"/>
        <rect x="28" y="62" width="40" height="30" rx="4" fill="#fff7ed" stroke="#78350f" stroke-width="3"/><path d="M38 73h20m-20 9h13m-4-21v-5h10v5" stroke="#a16207" stroke-width="3" stroke-linecap="round"/><path d="m55 82 4 4 8-9" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round"/>
      {/if}
    </svg>
  {:else}
    <span class="flex size-full items-center justify-center rounded-full border-2 border-primary/20 bg-primary/15 font-bold text-foreground">{initials}</span>
  {/if}
</span>
