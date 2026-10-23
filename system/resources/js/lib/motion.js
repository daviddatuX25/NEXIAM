/**
 * Motion.dev (vanilla) helpers for Svelte — scroll/in-view reveals.
 * Prefer restrained institutional motion (opacity + small translate).
 */
import { animate, inView } from 'motion';

/**
 * Svelte action: reveal element when it enters the viewport.
 * @param {HTMLElement} node
 * @param {{ y?: number, duration?: number, delay?: number, amount?: number | 'some' | 'all' }} [opts]
 */
export function reveal(node, opts = {}) {
  const y = opts.y ?? 18;
  const duration = opts.duration ?? 0.55;
  const delay = opts.delay ?? 0;
  const amount = opts.amount ?? 0.25;

  if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    node.style.opacity = '';
    node.style.transform = '';
    return {};
  }

  node.style.opacity = '0';
  node.style.transform = `translateY(${y}px)`;

  const stop = inView(
    node,
    (element) => {
      const anim = animate(
        element,
        { opacity: 1, transform: 'translateY(0px)' },
        { duration, delay, easing: [0.22, 1, 0.36, 1] }
      );
      anim.finished.then(() => {
        element.style.transform = '';
        element.style.opacity = '';
      });
    },
    { amount }
  );

  return {
    destroy() {
      stop();
    },
  };
}

/**
 * Stagger children with data-reveal attribute when parent enters view.
 * @param {HTMLElement} node
 * @param {{ selector?: string, y?: number, duration?: number, stagger?: number }} [opts]
 */
export function revealChildren(node, opts = {}) {
  const selector = opts.selector ?? '[data-reveal]';
  const y = opts.y ?? 16;
  const duration = opts.duration ?? 0.5;
  const stagger = opts.stagger ?? 0.08;

  if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return {};
  }

  const kids = /** @type {HTMLElement[]} */ ([...node.querySelectorAll(selector)]);
  kids.forEach((el) => {
    el.style.opacity = '0';
    el.style.transform = `translateY(${y}px)`;
  });

  const stop = inView(
    node,
    () => {
      kids.forEach((el, i) => {
        const anim = animate(
          el,
          { opacity: 1, transform: 'translateY(0px)' },
          { duration, delay: i * stagger, easing: [0.22, 1, 0.36, 1] }
        );
        anim.finished.then(() => {
          el.style.transform = '';
          el.style.opacity = '';
        });
      });
    },
    { amount: 0.2 }
  );

  return {
    destroy() {
      stop();
    },
  };
}
