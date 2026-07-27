---
name: Add phone, address and terms checkbox to apply form
status: complete
quick_id: 260415-ff2
---

## Summary

### Tasks Completed

1. **Add phone and address fields to Apply.svelte UI**
   - Added collapsible "Contact Information (optional)" `<details>` section between sex fields and course preferences
   - Contains: phone (tel input), address_line, city, province, zip_code fields in a responsive grid
   - All fields are bound to existing `$form` data properties already validated by `StoreApplicationRequest`

2. **Add terms and conditions checkbox**
   - Added `terms_accepted: false` to form data
   - Added `canSubmit = $derived($form.terms_accepted && !$form.processing)` reactive derived value
   - Submit button now uses `disabled={!canSubmit}` — prevents submission unless checkbox is checked
   - Terms checkbox styled with links to Terms and Conditions and Privacy Policy

### Files Changed

- `resources/js/Pages/Applications/Apply.svelte`

### Notes

- Backend validation (`StoreApplicationRequest`) already had `phone`, `address_line`, `city`, `province`, `zip_code` as nullable fields — no backend changes needed
- The `terms_accepted` field is added to the form but not yet validated server-side — if a policy needs server-side enforcement, add to `StoreApplicationRequest`
