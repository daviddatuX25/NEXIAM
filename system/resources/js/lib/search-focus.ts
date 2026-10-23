export function applySearchFocus(targetElementId: string): void {
  if (typeof window === 'undefined') return;

  const el = document.getElementById(targetElementId);
  if (!el) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  el.scrollIntoView({
    block: 'center',
    inline: 'nearest',
    behavior: prefersReducedMotion ? 'auto' : 'smooth',
  });

  el.setAttribute('tabindex', '-1');
  el.focus({ preventScroll: true });

  if (prefersReducedMotion) {
    el.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
  } else {
    el.classList.add('ring-2', 'ring-primary', 'ring-offset-2', 'bg-primary/10', 'transition-all', 'duration-500');
    setTimeout(() => {
      el.classList.remove('bg-primary/10');
    }, 1200);
  }
}
