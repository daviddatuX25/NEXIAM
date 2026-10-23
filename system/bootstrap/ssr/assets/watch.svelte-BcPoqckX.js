import "clsx";
const defaultWindow = void 0;
function getActiveElement(document) {
  let activeElement = document.activeElement;
  while (activeElement?.shadowRoot) {
    const node = activeElement.shadowRoot.activeElement;
    if (node === activeElement)
      break;
    else
      activeElement = node;
  }
  return activeElement;
}
const SvelteDate = globalThis.Date;
const SvelteSet = globalThis.Set;
const SvelteMap = globalThis.Map;
const SvelteURLSearchParams = globalThis.URLSearchParams;
function createSubscriber(_) {
  return () => {
  };
}
class ActiveElement {
  #document;
  #subscribe;
  constructor(options = {}) {
    const { window = defaultWindow, document = window?.document } = options;
    if (window === void 0) return;
    this.#document = document;
    this.#subscribe = createSubscriber();
  }
  get current() {
    this.#subscribe?.();
    if (!this.#document) return null;
    return getActiveElement(this.#document);
  }
}
new ActiveElement();
function runWatcher(sources, flush, effect, options = {}) {
  const { lazy = false } = options;
}
function watch(sources, effect, options) {
  runWatcher(sources, "post", effect, options);
}
function watchPre(sources, effect, options) {
  runWatcher(sources, "pre", effect, options);
}
watch.pre = watchPre;
export {
  SvelteMap as S,
  SvelteDate as a,
  SvelteSet as b,
  createSubscriber as c,
  defaultWindow as d,
  SvelteURLSearchParams as e,
  watch as w
};
