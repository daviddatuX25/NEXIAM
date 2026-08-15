---
paths:
  - 'resources/js/Pages/**/*.svelte'
---

# Pages

## PageHeaderBar actions go in the actions snippet, never as default-slot children
PageHeaderBar (Components/kit/PageHeaderBar.svelte) renders buttons ONLY via {#snippet actions()}, not default-slot children — direct children silently disappear (browser-verified 2026-08-14). Put the snippet after the element's closing '>'. Breadcrumbs passed to PageHeaderBar are ignored; the component has no breadcrumb rendering.
