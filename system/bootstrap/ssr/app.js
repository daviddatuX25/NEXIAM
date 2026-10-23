import axios from "axios";
import { createLayoutPropsStore, router, isPropsObjectOrCallback, isPropsObject, normalizeLayouts, http, exposeInterceptors, buildSSRBody, resolveServerHead, getInitialPageFromDOM, createHeadManager, setupProgress, config as config$1 } from "@inertiajs/core";
import { clsx as clsx$1 } from "clsx";
import "laravel-precognition";
import { twMerge } from "tailwind-merge";
import { tv } from "tailwind-variants";
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
let refreshingOn419 = false;
window.axios.interceptors.response.use(
  (response) => response,
  (error2) => {
    if (error2.response?.status === 419 && !refreshingOn419) {
      refreshingOn419 = true;
      window.location.reload();
    }
    return Promise.reject(error2);
  }
);
const ELEMENT_IS_NAMESPACED = 1;
const ELEMENT_PRESERVE_ATTRIBUTE_CASE = 1 << 1;
const ELEMENT_IS_INPUT = 1 << 2;
const ATTACHMENT_KEY = "@attach";
const ATTR_REGEX = /[&"<]/g;
const CONTENT_REGEX = /[&<]/g;
function escape_html(value, is_attr) {
  const str = String(value ?? "");
  const pattern = is_attr ? ATTR_REGEX : CONTENT_REGEX;
  pattern.lastIndex = 0;
  let escaped = "";
  let last = 0;
  while (pattern.test(str)) {
    const i = pattern.lastIndex - 1;
    const ch = str[i];
    escaped += str.substring(last, i) + (ch === "&" ? "&amp;" : ch === '"' ? "&quot;" : "&lt;");
    last = i + 1;
  }
  return escaped + str.substring(last);
}
var is_array = Array.isArray;
var define_property = Object.defineProperty;
var object_prototype = Object.prototype;
var get_prototype_of = Object.getPrototypeOf;
var has_own_property = Object.prototype.hasOwnProperty;
const noop = () => {
};
function run(fn) {
  return fn();
}
function run_all(arr) {
  for (var i = 0; i < arr.length; i++) {
    arr[i]();
  }
}
function fallback(value, fallback2, lazy = false) {
  return value === void 0 ? lazy ? (
    /** @type {() => V} */
    fallback2()
  ) : (
    /** @type {V} */
    fallback2
  ) : value;
}
const replacements = {
  translate: /* @__PURE__ */ new Map([
    [true, "yes"],
    [false, "no"]
  ])
};
function attr(name, value, is_boolean = false) {
  if (name === "hidden" && value !== "until-found") {
    is_boolean = true;
  }
  if (value == null || !value && is_boolean) return "";
  const normalized = has_own_property.call(replacements, name) && replacements[name].get(value) || value;
  const assignment = is_boolean ? `=""` : `="${escape_html(normalized, true)}"`;
  return ` ${name}${assignment}`;
}
function clsx(value) {
  if (typeof value === "object") {
    return clsx$1(value);
  } else {
    return value ?? "";
  }
}
const whitespace = [..." 	\n\r\f \v\uFEFF"];
function to_class(value, hash, directives) {
  var classname = value == null ? "" : "" + value;
  if (hash) {
    classname = classname ? classname + " " + hash : hash;
  }
  if (directives) {
    for (var key of Object.keys(directives)) {
      if (directives[key]) {
        classname = classname ? classname + " " + key : key;
      } else if (classname.length) {
        var len = key.length;
        var a = 0;
        while ((a = classname.indexOf(key, a)) >= 0) {
          var b = a + len;
          if ((a === 0 || whitespace.includes(classname[a - 1])) && (b === classname.length || whitespace.includes(classname[b]))) {
            classname = (a === 0 ? "" : classname.substring(0, a)) + classname.substring(b + 1);
          } else {
            a = b;
          }
        }
      }
    }
  }
  return classname === "" ? null : classname;
}
function append_styles(styles, important = false) {
  var separator = important ? " !important;" : ";";
  var css = "";
  for (var key of Object.keys(styles)) {
    var value = styles[key];
    if (value != null && value !== "") {
      css += " " + key + ": " + value + separator;
    }
  }
  return css;
}
function to_css_name(name) {
  if (name[0] !== "-" || name[1] !== "-") {
    return name.toLowerCase();
  }
  return name;
}
function to_style(value, styles) {
  if (styles) {
    var new_style = "";
    var normal_styles;
    var important_styles;
    if (Array.isArray(styles)) {
      normal_styles = styles[0];
      important_styles = styles[1];
    } else {
      normal_styles = styles;
    }
    if (value) {
      value = String(value).replaceAll(/\s*\/\*.*?\*\/\s*/g, "").trim();
      var in_str = false;
      var in_apo = 0;
      var in_comment = false;
      var reserved_names = [];
      if (normal_styles) {
        reserved_names.push(...Object.keys(normal_styles).map(to_css_name));
      }
      if (important_styles) {
        reserved_names.push(...Object.keys(important_styles).map(to_css_name));
      }
      var start_index = 0;
      var name_index = -1;
      const len = value.length;
      for (var i = 0; i < len; i++) {
        var c = value[i];
        if (in_comment) {
          if (c === "/" && value[i - 1] === "*") {
            in_comment = false;
          }
        } else if (in_str) {
          if (in_str === c) {
            in_str = false;
          }
        } else if (c === "/" && value[i + 1] === "*") {
          in_comment = true;
        } else if (c === '"' || c === "'") {
          in_str = c;
        } else if (c === "(") {
          in_apo++;
        } else if (c === ")") {
          in_apo--;
        }
        if (!in_comment && in_str === false && in_apo === 0) {
          if (c === ":" && name_index === -1) {
            name_index = i;
          } else if (c === ";" || i === len - 1) {
            if (name_index !== -1) {
              var name = to_css_name(value.substring(start_index, name_index).trim());
              if (!reserved_names.includes(name)) {
                if (c !== ";") {
                  i++;
                }
                var property = value.substring(start_index, i).trim();
                new_style += " " + property + ";";
              }
            }
            start_index = i + 1;
            name_index = -1;
          }
        }
      }
    }
    if (normal_styles) {
      new_style += append_styles(normal_styles);
    }
    if (important_styles) {
      new_style += append_styles(important_styles, true);
    }
    new_style = new_style.trim();
    return new_style === "" ? null : new_style;
  }
  return value == null ? null : String(value);
}
function lifecycle_outside_component(name) {
  {
    throw new Error(`https://svelte.dev/e/lifecycle_outside_component`);
  }
}
function safe_not_equal(a, b) {
  return a != a ? b == b : a !== b || a !== null && typeof a === "object" || typeof a === "function";
}
const subscriber_queue = [];
function writable(value, start = noop) {
  let stop = null;
  const subscribers = /* @__PURE__ */ new Set();
  function set(new_value) {
    if (safe_not_equal(value, new_value)) {
      value = new_value;
      if (stop) {
        const run_queue = !subscriber_queue.length;
        for (const subscriber of subscribers) {
          subscriber[1]();
          subscriber_queue.push(subscriber, value);
        }
        if (run_queue) {
          for (let i = 0; i < subscriber_queue.length; i += 2) {
            subscriber_queue[i][0](subscriber_queue[i + 1]);
          }
          subscriber_queue.length = 0;
        }
      }
    }
  }
  function update(fn) {
    set(fn(
      /** @type {T} */
      value
    ));
  }
  function subscribe(run2, invalidate = noop) {
    const subscriber = [run2, invalidate];
    subscribers.add(subscriber);
    if (subscribers.size === 1) {
      stop = start(set, update) || noop;
    }
    run2(
      /** @type {T} */
      value
    );
    return () => {
      subscribers.delete(subscriber);
      if (subscribers.size === 0 && stop) {
        stop();
        stop = null;
      }
    };
  }
  return { set, update, subscribe };
}
let untracking = false;
function untrack(fn) {
  var previous_untracking = untracking;
  try {
    untracking = true;
    return fn();
  } finally {
    untracking = previous_untracking;
  }
}
function subscribe_to_store(store2, run2, invalidate) {
  if (store2 == null) {
    run2(void 0);
    return noop;
  }
  const unsub = untrack(
    () => store2.subscribe(
      run2,
      // @ts-expect-error
      invalidate
    )
  );
  return unsub.unsubscribe ? () => unsub.unsubscribe() : unsub;
}
const EMPTY_COMMENT = `<!---->`;
const VOID_ELEMENT_NAMES = [
  "area",
  "base",
  "br",
  "col",
  "command",
  "embed",
  "hr",
  "img",
  "input",
  "keygen",
  "link",
  "meta",
  "param",
  "source",
  "track",
  "wbr"
];
function is_void(name) {
  return VOID_ELEMENT_NAMES.includes(name) || name.toLowerCase() === "!doctype";
}
const DOM_BOOLEAN_ATTRIBUTES = [
  "allowfullscreen",
  "async",
  "autofocus",
  "autoplay",
  "checked",
  "controls",
  "default",
  "disabled",
  "formnovalidate",
  "indeterminate",
  "inert",
  "ismap",
  "loop",
  "multiple",
  "muted",
  "nomodule",
  "novalidate",
  "open",
  "playsinline",
  "readonly",
  "required",
  "reversed",
  "seamless",
  "selected",
  "webkitdirectory",
  "defer",
  "disablepictureinpicture",
  "disableremoteplayback"
];
function is_boolean_attribute(name) {
  return DOM_BOOLEAN_ATTRIBUTES.includes(name);
}
const RAW_TEXT_ELEMENTS = (
  /** @type {const} */
  ["textarea", "script", "style", "title"]
);
function is_raw_text_element(name) {
  return RAW_TEXT_ELEMENTS.includes(
    /** @type {typeof RAW_TEXT_ELEMENTS[number]} */
    name
  );
}
const REGEX_VALID_TAG_NAME = /^[a-zA-Z][a-zA-Z0-9]*(-[a-zA-Z0-9.\-_\u00B7\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u037D\u037F-\u1FFF\u200C-\u200D\u203F-\u2040\u2070-\u218F\u2C00-\u2FEF\u3001-\uD7FF\uF900-\uFDCF\uFDF0-\uFFFD\u{10000}-\u{EFFFF}]*)?$/u;
function dynamic_element_invalid_tag(tag) {
  const error2 = new Error(`dynamic_element_invalid_tag
\`<svelte:element this="${tag}">\` is not a valid element name — the element will not be rendered
https://svelte.dev/e/dynamic_element_invalid_tag`);
  error2.name = "Svelte error";
  throw error2;
}
function lifecycle_function_unavailable(name) {
  const error2 = new Error(`lifecycle_function_unavailable
\`${name}(...)\` is not available on the server
https://svelte.dev/e/lifecycle_function_unavailable`);
  error2.name = "Svelte error";
  throw error2;
}
var ssr_context = null;
function getContext(key) {
  const context_map = get_or_init_context_map();
  const result = (
    /** @type {T} */
    context_map.get(key)
  );
  return result;
}
function setContext(key, context) {
  get_or_init_context_map().set(key, context);
  return context;
}
function hasContext(key) {
  return get_or_init_context_map().has(key);
}
function getAllContexts() {
  return get_or_init_context_map();
}
function get_or_init_context_map(name) {
  {
    lifecycle_outside_component();
  }
  return ssr_context.c ??= new Map(get_parent_context(ssr_context) || void 0);
}
function get_parent_context(ssr_context2) {
  let parent = ssr_context2.p;
  while (parent !== null) {
    const context_map = parent.c;
    if (context_map !== null) {
      return context_map;
    }
    parent = parent.p;
  }
  return null;
}
const INVALID_ATTR_NAME_CHAR_REGEX = /[\s'">/=\u{FDD0}-\u{FDEF}\u{FFFE}\u{FFFF}\u{1FFFE}\u{1FFFF}\u{2FFFE}\u{2FFFF}\u{3FFFE}\u{3FFFF}\u{4FFFE}\u{4FFFF}\u{5FFFE}\u{5FFFF}\u{6FFFE}\u{6FFFF}\u{7FFFE}\u{7FFFF}\u{8FFFE}\u{8FFFF}\u{9FFFE}\u{9FFFF}\u{AFFFE}\u{AFFFF}\u{BFFFE}\u{BFFFF}\u{CFFFE}\u{CFFFF}\u{DFFFE}\u{DFFFF}\u{EFFFE}\u{EFFFF}\u{FFFFE}\u{FFFFF}\u{10FFFE}\u{10FFFF}]/u;
function element(renderer, tag, attributes_fn = noop, children_fn = noop) {
  renderer.push("<!---->");
  if (tag) {
    if (!REGEX_VALID_TAG_NAME.test(tag)) {
      dynamic_element_invalid_tag(tag);
    }
    renderer.push(`<${tag}`);
    attributes_fn();
    renderer.push(`>`);
    if (!is_void(tag)) {
      children_fn();
      if (!is_raw_text_element(tag)) {
        renderer.push(EMPTY_COMMENT);
      }
      renderer.push(`</${tag}>`);
    }
  }
  renderer.push("<!---->");
}
function head(hash, renderer, fn) {
  renderer.head((renderer2) => {
    renderer2.push(`<!--${hash}-->`);
    renderer2.child(fn);
    renderer2.push(EMPTY_COMMENT);
  });
}
function attributes(attrs, css_hash, classes, styles, flags = 0) {
  if (attrs.class) {
    attrs.class = clsx(attrs.class);
  }
  let attr_str = "";
  let name;
  const is_html = (flags & ELEMENT_IS_NAMESPACED) === 0;
  const lowercase = (flags & ELEMENT_PRESERVE_ATTRIBUTE_CASE) === 0;
  const is_input = (flags & ELEMENT_IS_INPUT) !== 0;
  for (name of Object.keys(attrs)) {
    if (typeof attrs[name] === "function") continue;
    if (name[0] === "$" && name[1] === "$") continue;
    if (name === "" || INVALID_ATTR_NAME_CHAR_REGEX.test(name)) continue;
    var value = attrs[name];
    var lower = name.toLowerCase();
    if (lowercase) name = lower;
    if (lower.length > 2 && lower.startsWith("on")) continue;
    if (is_input) {
      if (name === "defaultvalue" || name === "defaultchecked") {
        name = name === "defaultvalue" ? "value" : "checked";
        if (attrs[name]) continue;
      }
    }
    attr_str += attr(name, value, is_html && is_boolean_attribute(name));
  }
  return attr_str;
}
function spread_props(props) {
  const merged_props = {};
  let key;
  for (let i = 0; i < props.length; i++) {
    const obj = props[i];
    if (obj == null) continue;
    for (key of Object.keys(obj)) {
      const desc = Object.getOwnPropertyDescriptor(obj, key);
      if (desc) {
        Object.defineProperty(merged_props, key, desc);
      } else {
        merged_props[key] = obj[key];
      }
    }
  }
  return merged_props;
}
function stringify(value) {
  return typeof value === "string" ? value : value == null ? "" : value + "";
}
function attr_class(value, hash, directives) {
  var result = to_class(value, hash, directives);
  return result ? ` class="${escape_html(result, true)}"` : "";
}
function attr_style(value, directives) {
  var result = to_style(value, directives);
  return result ? ` style="${escape_html(result, true)}"` : "";
}
function store_get(store_values, store_name, store2) {
  if (store_name in store_values && store_values[store_name][0] === store2) {
    return store_values[store_name][2];
  }
  store_values[store_name]?.[1]();
  store_values[store_name] = [store2, null, void 0];
  const unsub = subscribe_to_store(
    store2,
    /** @param {any} v */
    (v) => store_values[store_name][2] = v
  );
  store_values[store_name][1] = unsub;
  return store_values[store_name][2];
}
function unsubscribe_stores(store_values) {
  for (const store_name of Object.keys(store_values)) {
    store_values[store_name][1]();
  }
}
function slot(renderer, $$props, name, slot_props, fallback_fn) {
  var slot_fn = $$props.$$slots?.[name];
  if (slot_fn === true) {
    slot_fn = $$props["children"];
  }
  if (slot_fn !== void 0) {
    slot_fn(renderer, slot_props);
  }
}
function rest_props(props, rest) {
  const rest_props2 = {};
  let key;
  for (key of Object.keys(props)) {
    if (!rest.includes(key)) {
      rest_props2[key] = props[key];
    }
  }
  return rest_props2;
}
function sanitize_props(props) {
  const { children, $$slots, ...sanitized } = props;
  return sanitized;
}
function bind_props(props_parent, props_now) {
  for (const key of Object.keys(props_now)) {
    const initial_value = props_parent[key];
    const value = props_now[key];
    if (initial_value === void 0 && value !== void 0 && Object.getOwnPropertyDescriptor(props_parent, key)?.set) {
      props_parent[key] = value;
    }
  }
}
function ensure_array_like(array_like_or_iterator) {
  if (array_like_or_iterator) {
    return array_like_or_iterator.length !== void 0 ? array_like_or_iterator : Array.from(array_like_or_iterator);
  }
  return [];
}
function props_id(renderer) {
  const uid = renderer.global.uid();
  renderer.push("<!--$" + uid + "-->");
  return uid;
}
function derived(fn) {
  const get_value = fn;
  let updated_value;
  return function(new_value) {
    if (arguments.length === 0) {
      return updated_value ?? get_value();
    }
    updated_value = new_value;
    return updated_value;
  };
}
const store = createLayoutPropsStore();
const storeState = { shared: {}, named: {} };
store.subscribe(() => {
  const snapshot = store.get();
  storeState.shared = snapshot.shared;
  storeState.named = snapshot.named;
});
function resetLayoutProps() {
  store.reset();
  const snapshot = store.get();
  storeState.shared = snapshot.shared;
  storeState.named = snapshot.named;
}
const page = { component: "", props: {}, url: "", version: null };
function setPage(newPage) {
  Object.assign(page, newPage);
}
function usePage() {
  return page;
}
const h = (component, propsOrChildren, childrenOrKey, key = null) => {
  const hasProps = typeof propsOrChildren === "object" && propsOrChildren !== null && !Array.isArray(propsOrChildren);
  return {
    component,
    key: hasProps ? key : typeof childrenOrKey === "number" ? childrenOrKey : null,
    props: hasProps ? propsOrChildren : {},
    children: hasProps ? Array.isArray(childrenOrKey) ? childrenOrKey : childrenOrKey !== null ? [childrenOrKey] : [] : Array.isArray(propsOrChildren) ? propsOrChildren : propsOrChildren !== null ? [propsOrChildren] : []
  };
};
function Render_1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const { component, props = {}, children = [], key = null } = $$props;
    if (component) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<!---->`);
      {
        if (children.length > 0) {
          $$renderer2.push("<!--[0-->");
          const SvelteComponent = component;
          if (SvelteComponent) {
            $$renderer2.push("<!--[-->");
            SvelteComponent($$renderer2, spread_props([
              props,
              {
                children: ($$renderer3) => {
                  $$renderer3.push(`<!--[-->`);
                  const each_array = ensure_array_like(children);
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let child = each_array[$$index];
                    Render_1($$renderer3, spread_props([child]));
                  }
                  $$renderer3.push(`<!--]-->`);
                },
                $$slots: { default: true }
              }
            ]));
            $$renderer2.push("<!--]-->");
          } else {
            $$renderer2.push("<!--[!-->");
            $$renderer2.push("<!--]-->");
          }
        } else {
          $$renderer2.push("<!--[-1-->");
          const SvelteComponent_1 = component;
          if (SvelteComponent_1) {
            $$renderer2.push("<!--[-->");
            SvelteComponent_1($$renderer2, spread_props([props]));
            $$renderer2.push("<!--]-->");
          } else {
            $$renderer2.push("<!--[!-->");
            $$renderer2.push("<!--]-->");
          }
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function App($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const {
      initialComponent,
      initialPage,
      resolveComponent,
      defaultLayout
    } = $$props;
    let component = initialComponent;
    let key = null;
    let page2 = { ...initialPage, flash: initialPage.flash ?? {} };
    let renderProps = derived(() => resolveRenderProps(component, page2, key));
    setPage(page2);
    const isServer = typeof window === "undefined";
    if (!isServer) {
      router.init({
        initialPage,
        resolveComponent,
        swapComponent: async (args) => {
          setPage(args.page);
          component = args.component;
          page2 = args.page;
          key = args.preserveState ? key : Date.now();
          if (!args.preserveState) {
            resetLayoutProps();
          }
        },
        onFlash: (flash) => {
          page2 = { ...page2, flash };
        }
      });
    }
    function isComponent(value) {
      if (!value) {
        return false;
      }
      if (typeof value === "function") {
        const fn = value;
        return fn.name !== "";
      }
      if (typeof value === "object" && "$$" in value) {
        return true;
      }
      return false;
    }
    function isRenderFunction(value) {
      return typeof value === "function" && value.length === 2 && typeof value.prototype === "undefined";
    }
    function resolveRenderProps(component2, page3, key2 = null) {
      const child = h(component2.default, page3.props, [], key2);
      if (component2.layout && isRenderFunction(component2.layout)) {
        return component2.layout(h, child);
      }
      let effectiveLayout;
      let callbackProps = null;
      const layoutValue = component2.layout;
      if (typeof layoutValue === "function" && layoutValue.length <= 1 && typeof layoutValue.prototype === "undefined") {
        const result = layoutValue(page3.props);
        if (isPropsObjectOrCallback(result, isComponent)) {
          effectiveLayout = defaultLayout?.(page3.component, page3);
          callbackProps = result;
        } else {
          effectiveLayout = result;
        }
      } else if (isPropsObject(layoutValue, isComponent)) {
        effectiveLayout = defaultLayout?.(page3.component, page3);
        callbackProps = layoutValue;
      } else {
        effectiveLayout = layoutValue ?? defaultLayout?.(page3.component, page3);
      }
      return effectiveLayout ? resolveLayout(effectiveLayout, child, page3.props, key2, !!component2.layout && !callbackProps, callbackProps) : child;
    }
    function resolveLayout(layout, child, pageProps, key2, isFromPage = true, callbackProps = null) {
      if (isFromPage && isRenderFunction(layout)) {
        return layout(h, child);
      }
      let layouts = normalizeLayouts(layout, isComponent, isFromPage ? isRenderFunction : void 0);
      if (callbackProps) {
        layouts = layouts.map((l) => ({ ...l, props: { ...l.props, ...callbackProps } }));
      }
      if (layouts.length > 0) {
        const dynamicProps = isServer ? { shared: {}, named: {} } : { shared: storeState.shared, named: storeState.named };
        return layouts.reduceRight(
          (child2, layout2) => {
            return {
              ...h(
                layout2.component,
                {
                  ...pageProps,
                  ...layout2.props,
                  ...dynamicProps.shared,
                  ...layout2.name ? dynamicProps.named[layout2.name] || {} : {}
                },
                [child2],
                key2
              ),
              name: layout2.name
            };
          },
          child
        );
      }
      return child;
    }
    Render_1($$renderer2, spread_props([renderProps()]));
  });
}
function onDestroy(fn) {
  /** @type {SSRContext} */
  ssr_context.r.on_destroy(fn);
}
function mount() {
  lifecycle_function_unavailable("mount");
}
function hydrate() {
  lifecycle_function_unavailable("hydrate");
}
function unmount() {
  lifecycle_function_unavailable("unmount");
}
async function tick() {
}
async function createInertiaApp({ id = "app", resolve, setup, progress = {}, page: page2, defaults = {}, nonce, http: http$1, layout, serverHead, withApp, dev = false } = {}) {
  config.replace(defaults);
  if (nonce) {
    config.set("nonce", nonce);
  }
  if (http$1) {
    http.setClient(http$1);
  }
  if (dev) {
    exposeInterceptors();
  }
  const isServer = typeof window === "undefined";
  const resolveComponent = (name, page22) => Promise.resolve(resolve(name, page22));
  if (isServer && !page2) {
    return async (page22, render) => {
      const initialComponent2 = await resolveComponent(page22.component, page22);
      const props2 = {
        initialPage: page22,
        initialComponent: initialComponent2,
        resolveComponent,
        defaultLayout: layout
      };
      let svelteApp;
      if (setup) {
        const result = await setup({ el: null, App, props: props2 });
        if (!result) {
          throw new Error("Inertia SSR setup function must return a render result ({ body, head })");
        }
        svelteApp = result;
      } else {
        const context = /* @__PURE__ */ new Map();
        if (withApp) {
          withApp(context, { ssr: true, page: page22 });
        }
        svelteApp = await render(App, { props: props2, context });
      }
      const body = buildSSRBody(id, page22, svelteApp.body);
      return {
        body,
        head: [...resolveServerHead(page22, serverHead), svelteApp.head]
      };
    };
  }
  const initialPage = page2 || getInitialPageFromDOM(id);
  const serverHeadManager = !isServer && serverHead ? createHeadManager(false, (title) => title, () => {
  }, resolveServerHead(initialPage, serverHead)) : null;
  const [initialComponent] = await Promise.all([
    resolveComponent(initialPage.component, initialPage),
    router.decryptHistory().catch(() => {
    })
  ]);
  const props = { initialPage, initialComponent, resolveComponent, defaultLayout: layout };
  if (isServer) {
    if (!setup) {
      throw new Error("Inertia SSR requires a setup function that returns a render result ({ body, head })");
    }
    const svelteApp = await setup({ el: null, App, props });
    if (svelteApp) {
      const body = buildSSRBody(id, initialPage, svelteApp.body);
      return {
        body,
        head: [...resolveServerHead(initialPage, serverHead), svelteApp.head]
      };
    }
    return;
  }
  const target = document.getElementById(id);
  if (setup) {
    await setup({ el: target, App, props });
  } else {
    const context = /* @__PURE__ */ new Map();
    if (withApp) {
      withApp(context, { ssr: false, page: initialPage });
    }
    if (target.hasAttribute("data-server-rendered")) {
      hydrate();
    } else {
      mount();
    }
  }
  if (serverHeadManager) {
    const syncServerHead = (event) => {
      serverHeadManager.updateServerHead(resolveServerHead(event.detail.page, serverHead));
    };
    router.on("navigate", syncServerHead);
    router.on("clientVisit", syncServerHead);
  }
  if (progress) {
    setupProgress(progress);
  }
}
const config = config$1.extend({});
async function resolvePageComponent(path, pages) {
  for (const p of Array.isArray(path) ? path : [path]) {
    const page2 = pages[p];
    if (typeof page2 === "undefined") {
      continue;
    }
    return typeof page2 === "function" ? page2() : page2;
  }
  throw new Error(`Page not found: ${path}`);
}
const STORAGE_KEY = "theme";
let preference = "system";
let resolvedTheme = "light";
let mediaQueryList = null;
let mediaQueryListener = null;
let storageListener = null;
let isInitialized = false;
function normalizePreference(raw) {
  if (raw === "dark" || raw === "light" || raw === "system") {
    return raw;
  }
  return "system";
}
function getStoredPreference() {
  if (typeof window === "undefined") return "system";
  try {
    return normalizePreference(window.localStorage?.getItem(STORAGE_KEY));
  } catch {
    return "system";
  }
}
function getSystemDark() {
  if (typeof window === "undefined") return false;
  try {
    return Boolean(window.matchMedia?.("(prefers-color-scheme: dark)")?.matches);
  } catch {
    return false;
  }
}
function commitTheme(pref, persist = false) {
  const systemDark = getSystemDark();
  const effectivePreference = pref;
  const appearance = effectivePreference === "dark" ? "dark" : effectivePreference === "light" ? "light" : systemDark ? "dark" : "light";
  preference = pref;
  resolvedTheme = appearance;
  if (typeof document !== "undefined") {
    document.documentElement.classList.toggle("dark", appearance === "dark");
  }
  if (persist && typeof window !== "undefined") {
    try {
      window.localStorage?.setItem(STORAGE_KEY, pref);
    } catch {
    }
  }
}
function handleStorageEvent(e) {
  if (e.key === STORAGE_KEY || e.key === null) {
    const freshPref = getStoredPreference();
    commitTheme(freshPref, false);
  }
}
function handleMediaChange(e) {
  if (preference === "system") {
    const appearance = e.matches ? "dark" : "light";
    resolvedTheme = appearance;
    if (typeof document !== "undefined") {
      document.documentElement.classList.toggle("dark", appearance === "dark");
    }
  }
}
function initializeTheme() {
  if (typeof window === "undefined" || typeof document === "undefined") {
    return;
  }
  if (isInitialized) {
    return;
  }
  const initialPref = getStoredPreference();
  commitTheme(initialPref, false);
  storageListener = handleStorageEvent;
  window.addEventListener("storage", storageListener);
  try {
    mediaQueryList = window.matchMedia("(prefers-color-scheme: dark)");
    mediaQueryListener = handleMediaChange;
    if (typeof mediaQueryList.addEventListener === "function") {
      mediaQueryList.addEventListener("change", mediaQueryListener);
    } else if (typeof mediaQueryList.addListener === "function") {
      mediaQueryList.addListener(mediaQueryListener);
    }
  } catch {
  }
  isInitialized = true;
}
function getThemePreference() {
  return preference;
}
function isDarkMode() {
  return resolvedTheme === "dark";
}
function setThemePreference(pref) {
  commitTheme(normalizePreference(pref), true);
}
function toggleTheme() {
  const nextAppearance = resolvedTheme === "dark" ? "light" : "dark";
  setThemePreference(nextAppearance);
}
const ALLOWED_SCALES = [100, 115, 125];
const scaleToPx = { 100: "14px", 115: "16.1px", 125: "17.5px" };
let currentScale = 100;
function getScale() {
  return currentScale;
}
function setScale(percent, persist = true) {
  currentScale = percent;
  if (typeof document !== "undefined") {
    document.documentElement.style.fontSize = scaleToPx[percent];
  }
  if (persist) {
    try {
      localStorage.setItem("app_scale", String(percent));
    } catch {
    }
  }
}
function resetScale() {
  setScale(100);
}
function zoomIn() {
  if (currentScale === 100) {
    setScale(115);
  } else if (currentScale === 115) {
    setScale(125);
  }
}
function zoomOut() {
  if (currentScale === 125) {
    setScale(115);
  } else if (currentScale === 115) {
    setScale(100);
  }
}
function canZoomIn() {
  return currentScale < 125;
}
function canZoomOut() {
  return currentScale > 100;
}
function initializeScale() {
  let storedScale = null;
  try {
    storedScale = localStorage.getItem("app_scale");
  } catch {
  }
  const scale = ALLOWED_SCALES.find((allowedScale) => String(allowedScale) === storedScale) ?? 100;
  currentScale = scale;
  if (typeof document !== "undefined") {
    document.documentElement.style.fontSize = scaleToPx[scale];
  }
}
let listeningBrowser = null;
let activeGuard = null;
let dispatcher = null;
function initializeNoticeStudioHistoryGuard(browser = window) {
  if (listeningBrowser === browser) return;
  if (listeningBrowser && dispatcher) listeningBrowser.removeEventListener("popstate", dispatcher, true);
  dispatcher = (event) => activeGuard?.(event);
  listeningBrowser = browser;
  browser.addEventListener("popstate", dispatcher, true);
}
class SoundService {
  constructor() {
    this.ctx = null;
    this.enabled = false;
    this.initialized = false;
    if (typeof window !== "undefined") {
      try {
        const stored = localStorage.getItem("snd_sine_enabled");
        this.enabled = stored === "true";
      } catch {
        this.enabled = false;
      }
    }
  }
  /**
   * Lazy-initialize AudioContext upon user gesture.
   */
  getContext() {
    if (typeof window === "undefined") return null;
    if (this.ctx) return this.ctx;
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      this.ctx = new AudioCtx();
      return this.ctx;
    } catch {
      return null;
    }
  }
  ensureContext() {
    const ctx = this.getContext();
    if (!ctx) return false;
    if (ctx.state === "suspended") {
      try {
        ctx.resume().catch(() => {
        });
      } catch {
      }
    }
    return true;
  }
  async playInactivityCue(isCurrent, schedule) {
    if (!this.enabled) return false;
    const ctx = this.getContext();
    if (!ctx) return false;
    try {
      if (ctx.state !== "running") {
        if (ctx.state === "closed" || typeof ctx.resume !== "function") return false;
        await ctx.resume();
      }
      if (!this.enabled || this.ctx !== ctx || ctx.state !== "running" || !isCurrent()) return false;
      schedule(ctx);
      return true;
    } catch {
      return false;
    }
  }
  isEnabled() {
    return this.enabled;
  }
  enable() {
    this.enabled = true;
    if (typeof window !== "undefined") {
      try {
        localStorage.setItem("snd_sine_enabled", "true");
      } catch {
      }
    }
    this.ensureContext();
    this.tick();
  }
  disable() {
    this.enabled = false;
    if (typeof window !== "undefined") {
      try {
        localStorage.setItem("snd_sine_enabled", "false");
      } catch {
      }
    }
  }
  toggle() {
    if (this.enabled) {
      this.disable();
    } else {
      this.enable();
    }
    return this.enabled;
  }
  /**
   * Tier 1: Micro Navigation Tick
   * For segmented dials, radio controls, tabs, and filters.
   * Transient sine blip: 1200Hz -> 900Hz, 14ms decay.
   */
  tick() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const t = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(1200, t);
      osc.frequency.exponentialRampToValueAtTime(900, t + 0.014);
      gain.gain.setValueAtTime(0.045, t);
      gain.gain.exponentialRampToValueAtTime(1e-4, t + 0.014);
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(t);
      osc.stop(t + 0.016);
    } catch (e) {
    }
  }
  /**
   * Tier 2: Tactile Button Press
   * For primary and authoritative command buttons.
   * Mechanical thud: 480Hz -> 240Hz, 32ms decay.
   */
  press() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const t = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(480, t);
      osc.frequency.exponentialRampToValueAtTime(240, t + 0.032);
      gain.gain.setValueAtTime(0.07, t);
      gain.gain.exponentialRampToValueAtTime(1e-4, t + 0.032);
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(t);
      osc.stop(t + 0.035);
    } catch (e) {
    }
  }
  /**
   * Tier 3: Operational Success Resolution
   * For completed task commits, affirmative toasts, and qualification releases.
   * 4-note ascending Major 9th arpeggio: C5 (523Hz) -> E5 (659Hz) -> G5 (784Hz) -> B5 (988Hz).
   */
  success() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const notes = [523.25, 659.25, 783.99, 987.77];
      notes.forEach((freq, i) => {
        const t = this.ctx.currentTime + i * 0.04;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(freq, t);
        gain.gain.setValueAtTime(0.05, t);
        gain.gain.exponentialRampToValueAtTime(1e-4, t + 0.1);
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start(t);
        osc.stop(t + 0.11);
      });
    } catch (e) {
    }
  }
  /**
   * Tier 4: Error Rejection Warning
   * For conflict alerts, invalid actions, and destructive dismissals.
   * Distinct two-tone descending minor dissonance:
   * Pulse 1: 420Hz -> 280Hz (35ms alert drop)
   * Pulse 2: 240Hz -> 150Hz (50ms rejection descent, 45ms gap)
   */
  error() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const t = this.ctx.currentTime;
      const osc1 = this.ctx.createOscillator();
      const gain1 = this.ctx.createGain();
      osc1.type = "sine";
      osc1.frequency.setValueAtTime(420, t);
      osc1.frequency.exponentialRampToValueAtTime(280, t + 0.035);
      gain1.gain.setValueAtTime(0.08, t);
      gain1.gain.exponentialRampToValueAtTime(1e-4, t + 0.035);
      osc1.connect(gain1);
      gain1.connect(this.ctx.destination);
      osc1.start(t);
      osc1.stop(t + 0.038);
      const t2 = t + 0.045;
      const osc2 = this.ctx.createOscillator();
      const gain2 = this.ctx.createGain();
      osc2.type = "sine";
      osc2.frequency.setValueAtTime(240, t2);
      osc2.frequency.exponentialRampToValueAtTime(150, t2 + 0.05);
      gain2.gain.setValueAtTime(0.09, t2);
      gain2.gain.exponentialRampToValueAtTime(1e-4, t2 + 0.05);
      osc2.connect(gain2);
      gain2.connect(this.ctx.destination);
      osc2.start(t2);
      osc2.stop(t2 + 0.055);
    } catch (e) {
    }
  }
  /**
   * Tier 5: Buoyant Drawer Pop
   * For modal sheets, Xiam assistant dock, and flyout navigation.
   * Upward frequency sweep: 360Hz -> 840Hz, 45ms duration.
   */
  pop() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const t = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(360, t);
      osc.frequency.exponentialRampToValueAtTime(840, t + 0.045);
      gain.gain.setValueAtTime(0.06, t);
      gain.gain.exponentialRampToValueAtTime(1e-4, t + 0.045);
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(t);
      osc.stop(t + 0.05);
    } catch (e) {
    }
  }
  /**
   * Tier 6: Mechanical Slot Latch Lock
   * For seating desk booking and room capacity reservation.
   * Dual latch tick: 900Hz and 1400Hz spaced 22ms apart.
   */
  slot() {
    if (!this.enabled || !this.ensureContext() || !this.ctx || this.ctx.state !== "running") return;
    try {
      const t = this.ctx.currentTime;
      [900, 1400].forEach((freq, i) => {
        const tSub = t + i * 0.022;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(freq, tSub);
        gain.gain.setValueAtTime(0.05, tSub);
        gain.gain.exponentialRampToValueAtTime(1e-4, tSub + 0.018);
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start(tSub);
        osc.stop(tSub + 0.02);
      });
    } catch (e) {
    }
  }
  /**
   * Tier 7: Gentle inactivity warning chime.
   */
  warning(isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      [523.25, 659.25].forEach((frequency, index) => {
        const start = t + index * 0.18;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(frequency, start);
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.035, start + 8e-3);
        gain.gain.exponentialRampToValueAtTime(1e-4, start + 0.15);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + 0.15);
      });
    });
  }
  /**
   * Tier 8: Urgent countdown pulse.
   */
  countdownUrgent(remainingSec, isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(660, t);
      gain.gain.setValueAtTime(0, t);
      gain.gain.linearRampToValueAtTime(0.035, t + 5e-3);
      gain.gain.exponentialRampToValueAtTime(1e-4, t + 0.08);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.08);
    });
  }
  /**
   * Tier 9: Session expired downward cadence.
   */
  expired(isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      [{ frequency: 392, duration: 0.18 }, { frequency: 261.63, duration: 0.22 }].forEach(({ frequency, duration }, index) => {
        const start = t + index * 0.2;
        const attack = 0.01;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(frequency, start);
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.04, start + attack);
        gain.gain.exponentialRampToValueAtTime(1e-4, start + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + duration);
      });
    });
  }
}
const sound = new SoundService();
function cn(...inputs) {
  return twMerge(clsx$1(inputs));
}
function newIdempotencyKey() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  const bytes = typeof crypto !== "undefined" && typeof crypto.getRandomValues === "function" ? crypto.getRandomValues(new Uint8Array(16)) : Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));
  bytes[6] = bytes[6] & 15 | 64;
  bytes[8] = bytes[8] & 63 | 128;
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function xsrfCookieValue() {
  if (typeof document === "undefined") {
    return "";
  }
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  if (!match || !match[1]) {
    return "";
  }
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}
function csrfToken() {
  if (typeof document === "undefined") {
    return "";
  }
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta && meta instanceof HTMLMetaElement && meta.content) {
    return meta.content;
  }
  const cookieValue = xsrfCookieValue();
  if (cookieValue) {
    return cookieValue;
  }
  if (typeof window !== "undefined" && window._token) {
    return window._token;
  }
  return "";
}
function csrfHeaders(fallbackToken = "") {
  const cookieValue = xsrfCookieValue();
  if (cookieValue) {
    return { "X-XSRF-TOKEN": cookieValue };
  }
  const token = fallbackToken || csrfToken();
  return token ? { "X-CSRF-TOKEN": token } : {};
}
const buttonVariants = tv({
  base: "focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 rounded-lg border border-transparent bg-clip-padding text-sm font-medium focus-visible:ring-3 active:not-aria-[haspopup]:translate-y-px aria-invalid:ring-3 [&_svg:not([class*='size-'])]:size-4 group/button inline-flex shrink-0 items-center justify-center whitespace-nowrap transition-all outline-none select-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0",
  variants: {
    variant: {
      default: "bg-primary text-primary-foreground font-bold border border-primary/85 shadow-sm shadow-primary/20 [background-image:linear-gradient(180deg,rgba(255,255,255,0.18)_0%,rgba(255,255,255,0)_100%)] [box-shadow:inset_0_1px_0_0_rgba(255,255,255,0.28),0_2px_4px_0_rgba(0,0,0,0.1),0_4px_12px_-2px_rgba(16,185,129,0.35)] hover:brightness-105 active:brightness-95",
      outline: "border-border/80 bg-card/85 text-foreground hover:bg-muted hover:border-primary/50 dark:bg-card/60 dark:border-border/80 dark:hover:bg-muted/40 [box-shadow:inset_0_1px_0_0_rgba(255,255,255,0.08),0_1px_2px_0_rgba(0,0,0,0.05)]",
      secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
      ghost: "hover:bg-muted hover:text-foreground dark:hover:bg-muted/50",
      destructive: "bg-destructive/10 hover:bg-destructive/20 text-destructive border-destructive/30",
      link: "text-primary underline-offset-4 hover:underline"
    },
    size: {
      default: "h-8 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
      xs: "h-6 gap-1 rounded-lg px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
      sm: "h-7 gap-1 rounded-lg px-2.5 text-[0.8rem] in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3.5",
      lg: "h-9 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
      icon: "size-8",
      "icon-xs": "size-6 rounded-lg in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3",
      "icon-sm": "size-7 rounded-lg in-data-[slot=button-group]:rounded-lg",
      "icon-lg": "size-9"
    }
  },
  defaultVariants: { variant: "default", size: "default" }
});
function Button($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      class: className,
      variant = "default",
      size = "default",
      ref = null,
      href = void 0,
      type = "button",
      disabled,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    if (href) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<a${attributes({
        "data-slot": "button",
        class: clsx(cn(buttonVariants({ variant, size }), "motion-safe:hover:-translate-y-0.5 motion-safe:active:scale-[0.96] transition-transform", className)),
        href: disabled ? void 0 : href,
        "aria-disabled": disabled,
        role: disabled ? "link" : void 0,
        tabindex: disabled ? -1 : void 0,
        ...restProps
      })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></a>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<button${attributes({
        "data-slot": "button",
        class: clsx(cn(buttonVariants({ variant, size }), "motion-safe:hover:-translate-y-0.5 motion-safe:active:scale-[0.96] transition-transform", className)),
        type,
        disabled,
        ...restProps
      })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></button>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
const DEFAULT_DURATIONS = {
  success: 6e3,
  info: 6e3,
  warning: 1e4,
  error: 12e3
};
const MAX_VISIBLE_TOASTS = 4;
const timers = /* @__PURE__ */ new Map();
function createToastId() {
  return Math.random().toString(36).slice(2) + Date.now().toString(36);
}
function clearTimer(id) {
  const timer = timers.get(id);
  if (timer === void 0) {
    return;
  }
  clearTimeout(timer);
  timers.delete(id);
}
function scheduleDismiss(id, duration, dismiss) {
  clearTimer(id);
  if (duration <= 0) {
    return;
  }
  timers.set(
    id,
    setTimeout(() => {
      timers.delete(id);
      dismiss(id);
    }, duration)
  );
}
function normalizeOptions(options) {
  return options && typeof options === "object" ? options : {};
}
function createToastStore() {
  const { subscribe, update } = writable([]);
  function dismissToast(id) {
    clearTimer(id);
    update((toasts2) => toasts2.filter((toast) => toast.id !== id));
  }
  function clearToasts() {
    update((toasts2) => {
      toasts2.forEach((toast) => clearTimer(toast.id));
      return [];
    });
  }
  return {
    subscribe,
    show(message, type = "info", duration, options = {}) {
      const normalizedOptions = normalizeOptions(options);
      const resolvedDuration = duration ?? DEFAULT_DURATIONS[type] ?? DEFAULT_DURATIONS.info;
      const hasDedupeKey = normalizedOptions.dedupeKey !== void 0 && normalizedOptions.dedupeKey !== null;
      const dedupeKey = normalizedOptions.dedupeKey;
      const candidate = {
        id: createToastId(),
        message,
        type,
        duration: resolvedDuration
      };
      if (type === "success") {
        sound.success?.();
      } else if (type === "error") {
        sound.error?.();
      } else {
        sound.press?.();
      }
      if (hasDedupeKey) {
        candidate.dedupeKey = dedupeKey;
      }
      let resultId = candidate.id;
      let shouldSchedule = true;
      update((currentToasts) => {
        const existingIndex = currentToasts.findIndex(
          (toast) => hasDedupeKey ? toast.dedupeKey === dedupeKey : toast.type === type && toast.message === message
        );
        if (existingIndex !== -1) {
          const existingToast = currentToasts[existingIndex];
          resultId = existingToast.id;
          if (!hasDedupeKey) {
            shouldSchedule = false;
            return currentToasts;
          }
          clearTimer(existingToast.id);
          const replacement = [...currentToasts];
          replacement[existingIndex] = {
            ...candidate,
            id: existingToast.id
          };
          return replacement;
        }
        const nextToasts = [...currentToasts, candidate];
        const overflow = nextToasts.length - MAX_VISIBLE_TOASTS;
        if (overflow <= 0) {
          return nextToasts;
        }
        nextToasts.slice(0, overflow).forEach((toast) => clearTimer(toast.id));
        return nextToasts.slice(overflow);
      });
      if (shouldSchedule) {
        scheduleDismiss(resultId, resolvedDuration, dismissToast);
      }
      return resultId;
    },
    dismiss: dismissToast,
    clear: clearToasts
  };
}
const toasts = createToastStore();
function success(message, options = {}) {
  const normalizedOptions = normalizeOptions(options);
  return toasts.show(message, "success", normalizedOptions.duration, normalizedOptions);
}
function error(message, options = {}) {
  const normalizedOptions = normalizeOptions(options);
  return toasts.show(message, "error", normalizedOptions.duration, normalizedOptions);
}
function info(message, options = {}) {
  const normalizedOptions = normalizeOptions(options);
  return toasts.show(message, "info", normalizedOptions.duration, normalizedOptions);
}
function warning(message, options = {}) {
  const normalizedOptions = normalizeOptions(options);
  return toasts.show(message, "warning", normalizedOptions.duration, normalizedOptions);
}
initializeTheme();
initializeScale();
initializeNoticeStudioHistoryGuard();
createInertiaApp({
  resolve: (name) => resolvePageComponent(
    `./Pages/${name}.svelte`,
    /* @__PURE__ */ Object.assign({ "./Pages/Admission/ApplicantAdmissionSupport.svelte": () => import("./assets/ApplicantAdmissionSupport-BhmswHTC.js"), "./Pages/Admission/Apply.svelte": () => import("./assets/Apply-D8OWcAvL.js"), "./Pages/Admission/Confirmation.svelte": () => import("./assets/Confirmation-B1YImf5u.js"), "./Pages/Admission/E2PageState.svelte": () => import("./assets/E2PageState-yFavAmgl.js"), "./Pages/Admission/Index.svelte": () => import("./assets/Index-B17vnbuk.js"), "./Pages/Admission/Inquiries/Index.svelte": () => import("./assets/Index-DUHAfaGK.js"), "./Pages/Admission/Inquiries/Show.svelte": () => import("./assets/Show-CNJ2MMfQ.js"), "./Pages/Admission/IntakeCorrectionFields.svelte": () => import("./assets/IntakeCorrectionFields-BT17XBCB.js"), "./Pages/Admission/Show.svelte": () => import("./assets/Show-BfV7LyM-.js"), "./Pages/Admission/ToastBridge.svelte": () => import("./assets/ToastBridge-CeYTgXTv.js"), "./Pages/Applicant/AdmissionSupport.svelte": () => import("./assets/AdmissionSupport-SoMCamdO.js"), "./Pages/Applicant/ApplicantLayout.svelte": () => import("./assets/ApplicantLayout-DKnOpLAK.js"), "./Pages/Applicant/Consultation.svelte": () => import("./assets/Consultation-DoS-3HXi.js"), "./Pages/Applicant/Correction.svelte": () => import("./assets/Correction-Zqd0yaEJ.js"), "./Pages/Applicant/Inquiries/Index.svelte": () => import("./assets/Index-YOwE6m09.js"), "./Pages/Applicant/Inquiries/Show.svelte": () => import("./assets/Show-BIam5L1x.js"), "./Pages/Applicant/Login.svelte": () => import("./assets/Login-DlRjlqb7.js"), "./Pages/Applicant/MyApplication.svelte": () => import("./assets/MyApplication-Ds4hvtyp.js"), "./Pages/Applicant/MyQr.svelte": () => import("./assets/MyQr-D2qOozBb.js"), "./Pages/Applicant/Portal.svelte": () => import("./assets/Portal-_-sfohSe.js"), "./Pages/Applicant/ProgramChoice.svelte": () => import("./assets/ProgramChoice-_WzzURMq.js"), "./Pages/Applicant/Schedule.svelte": () => import("./assets/Schedule-D7awK77e.js"), "./Pages/Applicant/SchedulePipeline.svelte": () => import("./assets/SchedulePipeline-B-MciuGr.js"), "./Pages/Applicant/Setup.svelte": () => import("./assets/Setup-jFYfLUnn.js"), "./Pages/Assessment/Grading.svelte": () => import("./assets/Grading-C5YDyT9b.js"), "./Pages/Assessment/GradingScoreInput.svelte": () => import("./assets/GradingScoreInput-DGNMfj3r.js"), "./Pages/Assessment/GradingSession.svelte": () => import("./assets/GradingSession-Db15jSx5.js"), "./Pages/Assessment/Omr/Capture.svelte": () => import("./assets/Capture-bqwczKMS.js"), "./Pages/Assessment/Omr/Scanner.svelte": () => import("./assets/Scanner-DTb7dPB0.js"), "./Pages/Auth/Login.svelte": () => import("./assets/Login-DW5lDG0y.js"), "./Pages/Auth/TwoFactorChallenge.svelte": () => import("./assets/TwoFactorChallenge-BkkYDwPJ.js"), "./Pages/Communications/AnnouncementForm.svelte": () => import("./assets/AnnouncementForm-DxnVdsul.js"), "./Pages/Communications/Create.svelte": () => import("./assets/Create-DjpzvEo6.js"), "./Pages/Communications/Edit.svelte": () => import("./assets/Edit-CPh45EvG.js"), "./Pages/Communications/Index.svelte": () => import("./assets/Index-DdJ65g_o.js"), "./Pages/Communications/Policies/Create.svelte": () => import("./assets/Create-DuQ-WaBg.js"), "./Pages/Communications/Policies/Edit.svelte": () => import("./assets/Edit-CQqO1BGu.js"), "./Pages/Communications/Policies/Index.svelte": () => import("./assets/Index-CIlYT5B2.js"), "./Pages/Communications/Policies/PolicyForm.svelte": () => import("./assets/PolicyForm-Dw5nTfmD.js"), "./Pages/Consultation/ConsultationSessionCreate.svelte": () => import("./assets/ConsultationSessionCreate-hWp569PI.js"), "./Pages/Consultation/ConsultationSessionEdit.svelte": () => import("./assets/ConsultationSessionEdit-CIJSROHe.js"), "./Pages/Consultation/ConsultationSessionShow.svelte": () => import("./assets/ConsultationSessionShow-BbHUGWB2.js"), "./Pages/Consultation/ConsultationSessions.svelte": () => import("./assets/ConsultationSessions-Ciczr8VH.js"), "./Pages/Consultation/Dashboard.svelte": () => import("./assets/Dashboard-ClFdx8E9.js"), "./Pages/Consultation/OnlineEncounter.svelte": () => import("./assets/OnlineEncounter-CA67GHwO.js"), "./Pages/Examination/Create.svelte": () => import("./assets/Create-BVbLdU1T.js"), "./Pages/Examination/DirectAssessment/Create.svelte": () => import("./assets/Create-CPrstV9h.js"), "./Pages/Examination/Edit.svelte": () => import("./assets/Edit-B7y1X-WW.js"), "./Pages/Examination/Index.svelte": () => import("./assets/Index-lKZ153N-.js"), "./Pages/Examination/Monitoring.svelte": () => import("./assets/Monitoring-DtrJJg2U.js"), "./Pages/Examination/MonitoringIndex.svelte": () => import("./assets/MonitoringIndex-ClzE3CeN.js"), "./Pages/Examination/Show.svelte": () => import("./assets/Show-Ceil90Zm.js"), "./Pages/Foundation/DemoGuide.svelte": () => import("./assets/DemoGuide-BzOuxKkT.js"), "./Pages/Foundation/DemoReferenceViewer.svelte": () => import("./assets/DemoReferenceViewer-BpDsrsw7.js"), "./Pages/Foundation/Glossary.svelte": () => import("./assets/Glossary-7-JzsCsW.js"), "./Pages/Foundation/PracticePreparation.svelte": () => import("./assets/PracticePreparation-0zagnJo-.js"), "./Pages/Foundation/PracticePreparationPanel.svelte": () => import("./assets/PracticePreparationPanel-CFZWv1Kg.js"), "./Pages/Foundation/ResearchFacilitatorGuide.svelte": () => import("./assets/ResearchFacilitatorGuide-Das28Wu9.js"), "./Pages/Foundation/ResearchSessionGuide.svelte": () => import("./assets/ResearchSessionGuide-CYNvLupf.js"), "./Pages/Foundation/Status.svelte": () => import("./assets/Status-DIvdvn4G.js"), "./Pages/Home.svelte": () => import("./assets/Home-CB9HO9K9.js"), "./Pages/Import/Index.svelte": () => import("./assets/Index-BlFmKN1H.js"), "./Pages/Import/Review.svelte": () => import("./assets/Review-C_x6dFUo.js"), "./Pages/Knowledge/Create.svelte": () => import("./assets/Create-Dpe-3i5S.js"), "./Pages/Knowledge/Index.svelte": () => import("./assets/Index-CfJnBWFZ.js"), "./Pages/Knowledge/Show.svelte": () => import("./assets/Show-BviugBHK.js"), "./Pages/Notifications/Index.svelte": () => import("./assets/Index-DxcSXgYv.js"), "./Pages/ProgramHead/ApplicantShow.svelte": () => import("./assets/ApplicantShow-VOx-tyf-.js"), "./Pages/ProgramHead/Index.svelte": () => import("./assets/Index-vcrrPAv_.js"), "./Pages/ProgramHead/ProgramShow.svelte": () => import("./assets/ProgramShow-s-Xnz-yM.js"), "./Pages/Public/Announcement.svelte": () => import("./assets/Announcement-C_LNcLga.js"), "./Pages/Public/Announcements.svelte": () => import("./assets/Announcements-DSrj2RDr.js"), "./Pages/Public/Policies.svelte": () => import("./assets/Policies-BV3MUqq3.js"), "./Pages/Public/Policy.svelte": () => import("./assets/Policy-CLDgGgnx.js"), "./Pages/Public/PrivacyPolicy.svelte": () => import("./assets/PrivacyPolicy-BWsuRXEM.js"), "./Pages/Public/Program.svelte": () => import("./assets/Program-DDc-RQZm.js"), "./Pages/Public/ProgramPolicies.svelte": () => import("./assets/ProgramPolicies-CD3B51eV.js"), "./Pages/Registrar/EnrollmentEligibility.svelte": () => import("./assets/EnrollmentEligibility-_6gddCxk.js"), "./Pages/Release/Index.svelte": () => import("./assets/Index-eBaya4BI.js"), "./Pages/Release/PrintBatch.svelte": () => import("./assets/PrintBatch-CwTwtfY1.js"), "./Pages/Release/ResultSheet.svelte": () => import("./assets/ResultSheet-Ca9mDyBc.js"), "./Pages/Release/ResultSheetBulk.svelte": () => import("./assets/ResultSheetBulk-D5-vkRvK.js"), "./Pages/Reports/Index.svelte": () => import("./assets/Index-D0pTwnYC.js"), "./Pages/Setup/AdmissionSupport/Index.svelte": () => import("./assets/Index-eMEisb1-.js"), "./Pages/Setup/AdmissionSupport/TabFmStudio.svelte": () => import("./assets/TabFmStudio-DD1NiakA.js"), "./Pages/Setup/AdmissionSupport/YukeeStudio.svelte": () => import("./assets/YukeeStudio-32_r6HbK.js"), "./Pages/Setup/AiCenter.svelte": () => import("./assets/AiCenter-BGWBzC2d.js"), "./Pages/Setup/Aptitude.svelte": () => import("./assets/Aptitude-CHLpYI_D.js"), "./Pages/Setup/AptitudeScoring.svelte": () => import("./assets/AptitudeScoring-DBrZL6-n.js"), "./Pages/Setup/Booklets.svelte": () => import("./assets/Booklets-CSblhhvS.js"), "./Pages/Setup/BookletsIndex.svelte": () => import("./assets/BookletsIndex-BsOR4AYx.js"), "./Pages/Setup/ConsultationPolicy.svelte": () => import("./assets/ConsultationPolicy-ClJpmeQy.js"), "./Pages/Setup/Cycles.svelte": () => import("./assets/Cycles-BtQctn69.js"), "./Pages/Setup/Cycles/Create.svelte": () => import("./assets/Create-Fbd-mwff.js"), "./Pages/Setup/Cycles/Edit.svelte": () => import("./assets/Edit-3wTNJ83J.js"), "./Pages/Setup/Cycles/Notifications.svelte": () => import("./assets/Notifications-DTj1A6QY.js"), "./Pages/Setup/Departments.svelte": () => import("./assets/Departments-wJWq3u7R.js"), "./Pages/Setup/ExamMaterials.svelte": () => import("./assets/ExamMaterials-CcxGfoYG.js"), "./Pages/Setup/Grades.svelte": () => import("./assets/Grades-lrANtbxU.js"), "./Pages/Setup/Index.svelte": () => import("./assets/Index-DpTWrplZ.js"), "./Pages/Setup/IntakeFields.svelte": () => import("./assets/IntakeFields-BT-GFMoJ.js"), "./Pages/Setup/NormPackages/Create.svelte": () => import("./assets/Create-ByoowgFf.js"), "./Pages/Setup/NormPackages/Edit.svelte": () => import("./assets/Edit-DfLuKx40.js"), "./Pages/Setup/NormPackages/Index.svelte": () => import("./assets/Index-B56UK9fp.js"), "./Pages/Setup/NormPackages/NormPackageEditor.svelte": () => import("./assets/NormPackageEditor-D6Rh_Mj5.js"), "./Pages/Setup/NormPackages/Show.svelte": () => import("./assets/Show-8539QhXM.js"), "./Pages/Setup/Notifications.svelte": () => import("./assets/Notifications-ChE70nOZ.js"), "./Pages/Setup/Notifications/Admissions.svelte": () => import("./assets/Admissions-DO-bUGf1.js"), "./Pages/Setup/Notifications/Examination.svelte": () => import("./assets/Examination-BBI4IG3Q.js"), "./Pages/Setup/Notifications/Occurrence.svelte": () => import("./assets/Occurrence-5q6SUPKN.js"), "./Pages/Setup/Notifications/Results.svelte": () => import("./assets/Results-CTJqNbBe.js"), "./Pages/Setup/Notifications/Templates/Message.svelte": () => import("./assets/Message-Bw54MTYt.js"), "./Pages/Setup/Notifications/Templates/MessageRuns.svelte": () => import("./assets/MessageRuns-C-ty_l7J.js"), "./Pages/Setup/Notifications/Templates/PhysicalDocument.svelte": () => import("./assets/PhysicalDocument-Bk8fStoy.js"), "./Pages/Setup/Notifications/Templates/Studio.svelte": () => import("./assets/Studio-Bx8d3RBT.js"), "./Pages/Setup/OmrConfiguration/Booklets/Create.svelte": () => import("./assets/Create-B2a78iZ4.js"), "./Pages/Setup/OmrConfiguration/Booklets/Index.svelte": () => import("./assets/Index-C8BRfP2m.js"), "./Pages/Setup/OmrConfiguration/Booklets/Show.svelte": () => import("./assets/Show-DqWuMRbc.js"), "./Pages/Setup/OmrConfiguration/Canonical/BlankInstanceHistoryPanel.svelte": () => import("./assets/BlankInstanceHistoryPanel-bm926Pvr.js"), "./Pages/Setup/OmrConfiguration/Canonical/BlankInstancePrintPanel.svelte": () => import("./assets/BlankInstancePrintPanel-BWqLawsb.js"), "./Pages/Setup/OmrConfiguration/Canonical/CanonicalWorkflow.svelte": () => import("./assets/CanonicalWorkflow-CUsEzDSW.js"), "./Pages/Setup/OmrConfiguration/Canonical/Index.svelte": () => import("./assets/Index-CZz7PYBh.js"), "./Pages/Setup/OmrConfiguration/Canonical/OmrPhysicalTestMethodCard.svelte": () => import("./assets/OmrPhysicalTestMethodCard-hibzJL3T.js"), "./Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte": () => import("./assets/OmrTemplateStudio-BBkx-Myi.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte": () => import("./assets/StudioBubblePanel-dUcvtVr8.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte": () => import("./assets/StudioCanvas-BPLCVpSp.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioComponentAppearanceControls.svelte": () => import("./assets/StudioComponentAppearanceControls-CMSXg1au.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte": () => import("./assets/StudioExportVerifyPanel-CZarLenW.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioFiducialInsetControls.svelte": () => import("./assets/StudioFiducialInsetControls-CdTiPYqb.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioFontPicker.svelte": () => import("./assets/StudioFontPicker-5wWjSEPZ.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioOverlayPanel.svelte": () => import("./assets/StudioOverlayPanel-DQo4NXMg.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioQrReservationControls.svelte": () => import("./assets/StudioQrReservationControls-C1jYP4Jw.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioShadingGuideModal.svelte": () => import("./assets/StudioShadingGuideModal-BfmTaRFq.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioTip.svelte": () => import("./assets/StudioTip-evy-6JcG.js"), "./Pages/Setup/OmrConfiguration/Canonical/StudioTypographyControls.svelte": () => import("./assets/StudioTypographyControls-D2aqSkxQ.js"), "./Pages/Setup/OmrConfiguration/Index.svelte": () => import("./assets/Index-B5g4FBky.js"), "./Pages/Setup/OmrConfiguration/Intake.svelte": () => import("./assets/Intake-DT3TmVRr.js"), "./Pages/Setup/OmrConfiguration/Pairs/Create.svelte": () => import("./assets/Create-21M9tZRt.js"), "./Pages/Setup/OmrConfiguration/Templates/Index.svelte": () => import("./assets/Index-KXpgrhdr.js"), "./Pages/Setup/OmrConfiguration/Templates/Show.svelte": () => import("./assets/Show-DE2LpT0t.js"), "./Pages/Setup/OmrConfiguration/Templates/TestCapturePanel.svelte": () => import("./assets/TestCapturePanel-COWb1P5y.js"), "./Pages/Setup/OmrTest.svelte": () => import("./assets/OmrTest-D3iaJfTd.js"), "./Pages/Setup/Privacy.svelte": () => import("./assets/Privacy-BtcJq37C.js"), "./Pages/Setup/ProgramPublicDetails.svelte": () => import("./assets/ProgramPublicDetails-Bq7K9MUE.js"), "./Pages/Setup/ProgramPublicDetailsPreview.svelte": () => import("./assets/ProgramPublicDetailsPreview-Dq-tagnE.js"), "./Pages/Setup/Programs.svelte": () => import("./assets/Programs-D34SdusV.js"), "./Pages/Setup/Programs/Hub.svelte": () => import("./assets/Hub-B_TRCE8I.js"), "./Pages/Setup/Quotas.svelte": () => import("./assets/Quotas-BFT4hPzi.js"), "./Pages/Setup/ReportTemplates/Create.svelte": () => import("./assets/Create-CZtk6y2V.js"), "./Pages/Setup/ReportTemplates/Edit.svelte": () => import("./assets/Edit-DviCRKGj.js"), "./Pages/Setup/ReportTemplates/Index.svelte": () => import("./assets/Index-i1LpV6TP.js"), "./Pages/Setup/ReportTemplates/Surface.svelte": () => import("./assets/Surface-D88s03hG.js"), "./Pages/Setup/Requirements.svelte": () => import("./assets/Requirements-CfBZcrmb.js"), "./Pages/Setup/ResultSheetTemplates/Create.svelte": () => import("./assets/Create-VLpojx9X.js"), "./Pages/Setup/ResultSheetTemplates/Edit.svelte": () => import("./assets/Edit-DeNXtul9.js"), "./Pages/Setup/ResultSheetTemplates/Index.svelte": () => import("./assets/Index-BZ1KIhKr.js"), "./Pages/Setup/Rooms/Create.svelte": () => import("./assets/Create-D7sGMG1o.js"), "./Pages/Setup/Rooms/Edit.svelte": () => import("./assets/Edit-BzzFmU-C.js"), "./Pages/Setup/Rooms/Index.svelte": () => import("./assets/Index-C-tuBo6K.js"), "./Pages/Setup/Rooms/RoomForm.svelte": () => import("./assets/RoomForm-BlqQFD72.js"), "./Pages/Setup/Rooms/Show.svelte": () => import("./assets/Show-Doiq-b_r.js"), "./Pages/Setup/SeatingArrangements/Create.svelte": () => import("./assets/Create-CZwYOW-j.js"), "./Pages/Setup/SeatingArrangements/Edit.svelte": () => import("./assets/Edit-C2uwnqhJ.js"), "./Pages/Setup/SeatingArrangements/Index.svelte": () => import("./assets/Index-BylESf45.js"), "./Pages/Setup/SeatingArrangements/SeatingArrangementForm.svelte": () => import("./assets/SeatingArrangementForm-BLmmZFaK.js"), "./Pages/Setup/SeatingArrangements/SeatingArrangementPreview.svelte": () => import("./assets/SeatingArrangementPreview-BlLfpBBH.js"), "./Pages/Setup/TestInstruments/Create.svelte": () => import("./assets/Create-BDL2Dczv.js"), "./Pages/Setup/TestInstruments/Edit.svelte": () => import("./assets/Edit-IKkcVn0x.js"), "./Pages/Setup/TestInstruments/Index.svelte": () => import("./assets/Index-tC7FjggG.js"), "./Pages/Setup/TestInstruments/Show.svelte": () => import("./assets/Show-30Tdke8A.js"), "./Pages/Setup/TestInstruments/TestInstrumentEditor.svelte": () => import("./assets/TestInstrumentEditor-C9E_5NSh.js"), "./Pages/Staff/AccountSetup.svelte": () => import("./assets/AccountSetup-t4UJokud.js"), "./Pages/Staff/Activate.svelte": () => import("./assets/Activate-BsGPLh1I.js"), "./Pages/Staff/ApplicantActionHub.svelte": () => import("./assets/ApplicantActionHub-CN7UFamo.js"), "./Pages/Staff/ApplicantScan.svelte": () => import("./assets/ApplicantScan-OXvH326P.js"), "./Pages/Staff/Home.svelte": () => import("./assets/Home-CHW9MTTt.js"), "./Pages/Staff/Logs/Index.svelte": () => import("./assets/Index-heiwZnYP.js"), "./Pages/Staff/Personnel/Create.svelte": () => import("./assets/Create-BHxH0EnT.js"), "./Pages/Staff/Personnel/Edit.svelte": () => import("./assets/Edit-l-IIk1ZL.js"), "./Pages/Staff/Personnel/Index.svelte": () => import("./assets/Index-YN2MRQzU.js"), "./Pages/Staff/Personnel/VoucherDialog.svelte": () => import("./assets/VoucherDialog-COLHI7dj.js"), "./Pages/Staff/Profile/AvatarSelector.svelte": () => import("./assets/AvatarSelector-DK2ir4i7.js"), "./Pages/Staff/Profile/Show.svelte": () => import("./assets/Show-BHrvcYKD.js"), "./Pages/Staff/StaffLayout.svelte": () => import("./assets/StaffLayout-CR-MZopa.js").then((n) => n.b), "./Pages/UiKit/Gallery.svelte": () => import("./assets/Gallery-BRJCkVfp.js") })
  ),
  setup({ el, App: App2, props }) {
    if (!el) {
      throw new Error("Inertia mount element was not found");
    }
    mount();
  },
  progress: {
    color: "#2563eb"
  }
});
export {
  getContext as $,
  clsx as A,
  Button as B,
  cn as C,
  props_id as D,
  config as E,
  toasts as F,
  setThemePreference as G,
  zoomOut as H,
  canZoomOut as I,
  zoomIn as J,
  canZoomIn as K,
  resetScale as L,
  getThemePreference as M,
  getScale as N,
  element as O,
  rest_props as P,
  fallback as Q,
  ATTACHMENT_KEY as R,
  run_all as S,
  define_property as T,
  getAllContexts as U,
  mount as V,
  unmount as W,
  is_array as X,
  get_prototype_of as Y,
  object_prototype as Z,
  hasContext as _,
  ensure_array_like as a,
  setContext as a0,
  toggleTheme as a1,
  isDarkMode as a2,
  csrfToken as a3,
  warning as a4,
  attr as b,
  attr_class as c,
  derived as d,
  escape_html as e,
  attr_style as f,
  sanitize_props as g,
  head as h,
  spread_props as i,
  slot as j,
  sound as k,
  bind_props as l,
  csrfHeaders as m,
  newIdempotencyKey as n,
  onDestroy as o,
  info as p,
  success as q,
  run as r,
  stringify as s,
  tick as t,
  usePage as u,
  error as v,
  writable as w,
  store_get as x,
  unsubscribe_stores as y,
  attributes as z
};
