# Register page – UX/UI assessment & plan

## 1. What makes the page feel crowded

- **Too many inputs in one scroll:** Six interactive elements (name, email, password, confirm, terms, submit) plus three social buttons and a sign-in link. On mobile this creates a long, dense form with no clear “chunking.”
- **Weak hierarchy:** Everything has similar visual weight (same spacing, no step indicator), so the eye doesn’t know where to start or what’s optional (social) vs required (email form).
- **Social and email form compete:** “Or continue with” plus three large buttons sit after the main CTA, so the page reads as one long block instead of “pick one path, then complete it.”
- **Tight vertical rhythm:** `space-y-6` and `space-y-8` are fine for desktop but on small viewports the form still feels like a single wall of controls.
- **No progress signal:** Users can’t see how much is left or that the flow has distinct parts (identity vs security).

---

## 2. Recommendations

### A. Two-step flow (recommended)

- **Step 1 – Identity:** Full name + Email only. One primary CTA: “Continue.” Optionally show social sign-up at the top (“Continue with Google/Facebook/Instagram”) and a divider “Or sign up with email” so the form is clearly the alternative path. Fewer fields per screen and a clear “step 1 of 2” sense.
- **Step 2 – Security:** Password + Confirm password + Terms checkbox. Primary CTA: “Create account.” Secondary: “Back” (to step 1) so they can fix name/email. No social buttons here; step 2 is only for email sign-up completion.

**Why 2 steps:** Splits “who you are” from “secure your account,” reduces visible fields per screen, and adds a simple progress cue (step indicator) without adding real complexity.

### B. Layout and spacing

- **Stepper:** Small “Step 1 of 2” / “Step 2 of 2” (and optional progress bar) at the top of the form area for clarity.
- **More breathing room:** Slightly larger gaps between logical blocks (e.g. header vs form, form vs social, form vs sign-in link). Keep AuthLayout; only adjust spacing inside the register content.
- **Social placement:** On step 1 only. Put social first, then “Or sign up with email,” then name + email + Continue. This reduces crowding and makes “email” the alternative path.

### C. What we keep

- Same AuthLayout, fields, validation, submit behavior, and API.
- No backend, auth service, or route changes.

---

## 3. Implementation plan (order of changes)

1. **Add step state** – `step` ref (1 | 2); default 1.
2. **Step 1 UI** – Header; optional error; social block (“Continue with Google/Facebook/Instagram”); divider “Or sign up with email”; Full name + Email; “Continue” button; “Already have an account? Sign in.”
3. **Step 2 UI** – “Step 2 of 2” label (and optional minimal progress); “Back” link; Password + Confirm password + Terms; “Create account” button; “Already have an account? Sign in.”
4. **Step 1 → 2** – “Continue” validates name + email (required), then sets `step = 2`. No API call yet.
5. **Step 2 → submit** – “Create account” runs existing `handleSubmit` (same API/store). “Back” sets `step = 1`.
6. **Spacing** – Increase spacing between major sections (e.g. social vs form, form vs footer link) so the page feels less dense.
7. **Error display** – Show error on the step where it’s relevant (e.g. API error on step 2; keep error area in one place for simplicity).

Result: Same behavior and API, less crowding, clearer hierarchy, and a simple two-step flow that works well on mobile.
