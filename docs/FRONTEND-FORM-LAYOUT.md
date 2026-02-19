# Frontend: Add/Edit form layout (shared pattern)

All **add** and **edit** forms in the app (restaurants, menu items, and any future entities) should share the same look and structure for consistency.

**Reference implementations:** `RestaurantFormView.vue`, `MenuItemFormView.vue`.

---

## Layout

- **Outer wrapper:** `max-w-3xl` so the form does not stretch on very wide screens. Add `pb-24` when the form is embedded in a layout that needs bottom padding (e.g. tab content).
- **Page header (when not embedded):**  
  - `mb-6 lg:mb-8`  
  - `h2`: `text-xl font-bold text-charcoal dark:text-white lg:text-2xl`  
  - Subtitle: `text-sm text-slate-500 dark:text-slate-400 mt-1`
- **Form:** `space-y-6 lg:space-y-8` between form-level error, sections, and action row.

---

## Loading & not found

- **Loading skeleton:** One or two blocks with `rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse` (e.g. `h-32`, `h-48`).
- **Not found:** Single card: `bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-8 text-center`, message + `AppBackLink`.

---

## Form-level error

- Block with `id` and `data-testid="form-error"`:  
  `p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm`  
- Use `sr-only` when there is no error so it stays available to screen readers.

---

## Sections (content cards)

- **Section container:**  
  `bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4`
- **Section heading:**  
  `h3` with `font-semibold text-charcoal dark:text-white flex items-center gap-2`, plus a leading Material icon: `material-icons text-slate-500 dark:text-slate-400` (e.g. `info`, `translate`, `restaurant`).
- Use `data-testid="form-section-*"` for key sections.

---

## Inputs

- Use `AppInput` where possible (same `rounded-lg`, ring, background as other forms).
- Raw `<input>` / `<select>` / `<textarea>`:  
  `rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary ... bg-background-light dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white`
- Labels: `block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1`, and associate with inputs via `for` / `id`.
- Inline errors: `text-xs text-red-600 dark:text-red-400 mt-1` with `role="alert"` and `id` for `aria-describedby` when there is an error.

---

## Action row

- Container: `flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3`
- **Cancel:** `router-link` or back action on the left: `AppButton variant="secondary"` with `min-h-[44px] w-full sm:w-auto`.
- **Submit:** On the right: `AppButton type="submit" variant="primary"` with `min-h-[44px] w-full sm:w-auto sm:shrink-0`, `data-testid="form-submit"`, and loading state (e.g. spinner + “Saving…”).

---

When adding new add/edit screens (e.g. categories, other modules), copy these patterns from the restaurant and menu item forms so the app keeps a consistent look.
