---
status: investigating
trigger: "form.data is not a function"
created: 2026-04-14T00:00:00Z
updated: 2026-04-14T00:00:00Z
---

## Current Focus

hypothesis: Fixed - inconsistent use of form.set() without $ prefix
test: N/A - fix applied
expecting: File upload should now work
next_action: Verify fix

## Symptoms

expected: File upload should work, form.data() method should be available
actual: TypeError: form.data is not a function
reproduction: Go to Admin > Result Sheet Templates > Create or Edit > try to import document
started: New issue, never worked

## Evidence

- timestamp: 2026-04-14
  checked: Create.svelte and Edit.svelte form handling
  found: Used form.set() without $ prefix (inconsistent with other calls using $form.post())
  implication: In Inertia Svelte, form.set() is a method on the reactive $form proxy, not the raw object

## Resolution

root_cause: Code used form.set() without $ prefix while all other form calls used $form.post(), $form.put() etc. In Inertia Svelte, form methods like .set() need the $ prefix to access the reactive proxy.
fix: Changed form.set('docx', ...) to $form.docx = ... (direct assignment to reactive proxy)
verification: Need user to test file upload in the browser
files_changed:
- resources/js/Pages/Admin/ResultSheetTemplates/Create.svelte
- resources/js/Pages/Admin/ResultSheetTemplates/Edit.svelte