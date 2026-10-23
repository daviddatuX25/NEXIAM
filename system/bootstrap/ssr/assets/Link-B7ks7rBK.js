import { O as element, d as derived, z as attributes } from "../app.js";
import { isUrlMethodPair } from "@inertiajs/core";
function Link($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      href = "",
      as = "a",
      data = {},
      method = "get",
      replace = false,
      preserveScroll = false,
      preserveState = null,
      preserveUrl = false,
      only = [],
      except = [],
      headers = {},
      queryStringArrayFormat = "brackets",
      async = false,
      prefetch = false,
      cacheFor = 0,
      cacheTags = [],
      viewTransition = false,
      component = void 0,
      instant = false,
      pageProps = null,
      children,
      $$slots,
      $$events,
      ...rest
    } = $$props;
    let _method = derived(() => isUrlMethodPair(href) ? href.method : method);
    let _href = derived(() => isUrlMethodPair(href) ? href.url : href);
    let asProp = derived(() => _method() !== "get" ? "button" : as.toLowerCase());
    let elProps = derived(() => ({ a: { href: _href() }, button: { type: "button" } })[asProp()] || {});
    element(
      $$renderer2,
      asProp(),
      () => {
        $$renderer2.push(`${attributes({ ...rest, ...elProps() })}`);
      },
      () => {
        children?.($$renderer2);
        $$renderer2.push(`<!---->`);
      }
    );
  });
}
export {
  Link as L
};
