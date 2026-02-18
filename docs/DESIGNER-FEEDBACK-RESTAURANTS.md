# Designer feedback: Restaurants module (frontend)

**Scope:** App layout restaurants flow — list, create/edit form, detail/manage (tabs: Profile, Menu, Settings), menu items list/form, languages & description.

---

## What’s working well

- **Mobile-first:** List and detail use single column; tabs are bottom-fixed on mobile with safe area; touch targets (min-h-[44px]) are consistent.
- **Clear hierarchy:** Page titles, section headings (Basic information, Advanced details), and “Web address” card make the flow scannable.
- **Empty state:** First-time “Add your first location” with clear CTA and light decorative treatment (plate circles) fits the product.
- **Loading & not-found:** Skeleton placeholders and “Restaurant not found” with back link are in place.
- **Form structure:** Basic vs Advanced (collapsible), validation feedback, inline errors, and optional fields are clearly separated.
- **Detail/Manage:** Hero (banner/logo) with “Tap to change logo & banner,” web address + copy, contact block, and secondary actions (Menu, Languages, Edit, Delete) are present.
- **Modals:** Logo/banner update and delete confirmation (type slug) are implemented with focus and escape handling.
- **Design system:** Shared primary/sage, charcoal, cream; AppButton, AppInput, AppBackLink; dark mode and consistent borders/radius.

---

## UX/UI improvement areas

1. **Form width on desktop**  
   Add/edit restaurant form should not span full width on very large viewports. Use a max-width container (e.g. `max-w-3xl`) so the form stays readable; already applied when not `embed` — verify behavior and consider applying in embed mode if used on wide screens.

2. **Active / selected states**  
   Do not use left borders (e.g. `border-l-*`) for active nav or tab states. Prefer background + text color or a different indicator (e.g. underline, pill) for clarity and consistency.

3. **Restaurant model**  
   Project rules require modeling every API entity. Add `frontend/src/models/Restaurant.js` with `fromApi()`, defaulted fields, and derived values; use it in list/detail/form instead of raw API payloads.

4. **List → detail → back**  
   Ensure “Back to restaurants” / breadcrumb and tab persistence (e.g. `?tab=profile`) work so users don’t lose context when navigating.

5. **Pagination**  
   List has Previous/Next; consider showing total count (“Page 1 of 3 · 42 restaurants”) and optional keyboard/screen-reader cues for pagination.

6. **Delete flow**  
   “Type slug to confirm” is good; ensure focus is trapped in the modal and focus returns to a sensible element on close.

7. **Accessibility**  
   Verify: form labels and `aria-describedby` for errors, modal `aria-labelledby`/`aria-describedby`, and that tab panels have correct roles/ids for tablist/tab/tabpanel.

---

## Consistency check

- **Spacing:** `p-4 lg:p-6`, `space-y-6`, `rounded-xl`/`rounded-2xl` are consistent across sections.
- **Copy:** “Your restaurants,” “Add restaurant,” “Web address,” “Tap to change logo & banner” are clear and on-brand.
- **Errors:** Inline field errors and form-level error block are present; ensure toasts are used for success only and not duplicate the form error area.

---

## Implementation verification (post–FE follow-up)

| Item | Status | Notes |
|------|--------|--------|
| **1. Form max-width on desktop** | Done | `RestaurantFormView` and `MenuItemFormView` use `max-w-3xl`; form does not stretch on wide viewports. |
| **2. No border-l on active** | Done | Nav: `bg-primary/10 text-primary` only. Tabs: pill style, no left border. |
| **3. Restaurant model** | Done | `Restaurant.js` with `fromApi()`, defaults, `toJSON()`. Used in list, detail, form, manage, menu items, content views. |
| **4. List → detail / tab persistence** | Done | `activeTab` synced with `route.query.tab`; back and tab state persist. |
| **5. Pagination** | Done | "Page X of Y · N restaurants", `role="navigation"`, `aria-label="Pagination"`, `aria-live="polite"`. |
| **6. Delete modal focus** | Done | Tab trap + Escape; focus returns to Delete button on close. |
| **7. Accessibility** | Done | Form error `id` + `described-by`; modals and tabs have correct ARIA; AppModal focus trap + restore. |

**Verdict:** Designer feedback has been implemented correctly. UI/UX and a11y expectations for the restaurants module are met.

---

*Generated for FE follow-up. Use the "Prompt to give the FE" section below when briefing the frontend.* Use the “Prompt to give the FE” section below when briefing the frontend.*
