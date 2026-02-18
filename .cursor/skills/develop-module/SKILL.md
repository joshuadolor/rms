---
name: develop-module
description: Runs the full feature pipeline for a module: backend implementation, backend review, BE unit tests, frontend implementation, FE and UI/UX review, then E2E tests. Use when the user asks to develop a module, implement a feature module, or says "develop the X module" (e.g. menu items, restaurants, combos).
---

# Develop Module — Full Pipeline

Run this workflow when the user asks to develop a named module (e.g. "Develop the menu items module"). **Delegate each step to the corresponding subagent**; do not perform the step yourself. Execute steps in order. **All project rules remain in effect for every subagent** — ensure subagents are instructed to follow them.

## Subagents used

The subagents for this pipeline are **globally declared** (user-level). Use these exact names when delegating:

| Step | Subagent name (global) | Use for |
|------|------------------------|--------|
| 1 | **BE-LARAVEL--architect** | Implement the backend for the module |
| 2 | **REVIEWER-senior-laravel-backend-architect** | Review backend work; output PASS or list of issues |
| 3 | **BE-LARAVEL--architect** | Add/run backend unit tests for the module |
| 4 | **FE-VUE-LARAVEL--frontend** | Implement the frontend for the module |
| 5 | **DESIGNER-WEB-UXUI** | Review frontend work; output PASS or list of issues |
| 6 | **TESTER-e2e-playwright** | Add/run E2E tests for the module |

When delegating, pass this project’s rules and context (see below) so the subagent follows them for this repo.

**Orchestration:** You are the parent. For each step, spawn the subagent by the name above with the context below. If a reviewer returns issues: send them to **BE-LARAVEL--architect** (backend) or **FE-VUE-LARAVEL--frontend** (frontend) to fix, then re-run **REVIEWER-senior-laravel-backend-architect** or **DESIGNER-WEB-UXUI** until PASS. Step 7 (definition of done) you do yourself.

## Rules that always apply

While following this skill, you must still follow every project rule:

- **`.cursor/rules/restaurant-management-system.mdc`** — Product vision, API modeling, services layer, E2E expectations, definition of done, MVP scope.
- **`.cursor/rules/api-reference-backend.mdc`** — No internal `id` in API responses (use `uuid` only); document every new/changed endpoint in `docs/API-REFERENCE.md` and changelog.
- **`.cursor/rules/api-reference-frontend.mdc`** — Use `docs/API-REFERENCE.md` for all API calls; match request/response shapes and error handling.
- **`.cursor/rules/frontend-forms-validation.mdc`** — Frontend validate before API; no native HTML validation; `novalidate` on forms; run `npm run test:e2e` before considering form workflows done.
- **`.cursor/rules/frontend-mobile-first.mdc`** — Mobile-first layout and touch targets for all frontend work.

If any step would violate a rule, correct the approach so it complies.

---

## Pipeline overview

```
Backend Dev → Backend Review → (pass → BE Unit Tests | fail → Backend Dev)
     → Frontend Dev → FE + UI/UX Review → (pass → E2E Tests | fail → Frontend Dev)
     → Done (ask user for confirmation)
```

---

## Step 1 — Backend: Implement the module

**Delegate to: BE-LARAVEL--architect**

Context to pass: module name (e.g. "menu items"); request to implement the backend (API, model, validation, controllers, routes). Instruct the subagent to follow `api-reference-backend.mdc` (no `id` in responses, document in `docs/API-REFERENCE.md` and changelog) and `restaurant-management-system.mdc`. When the subagent finishes, proceed to Step 2.

---

## Step 2 — Backend: Review

**Delegate to: REVIEWER-senior-laravel-backend-architect**

Context to pass: the backend changes from Step 1. Instruct the subagent to check against `api-reference-backend.mdc` (security, documentation) and `restaurant-management-system.mdc`, and to output either **PASS** or a clear list of **issues** (to fix).  
- **If PASS:** Proceed to Step 3.  
- **If issues:** Hand the issues back to **BE-LARAVEL--architect** to fix, then run **REVIEWER-senior-laravel-backend-architect** again (repeat until PASS), then proceed to Step 3.

---

## Step 3 — Backend: Unit tests

**Delegate to: BE-LARAVEL--architect**

Context to pass: the module name and backend scope from Steps 1–2. Instruct the subagent to add or update backend unit tests for the module and run the test suite until it passes. When the subagent finishes, proceed to Step 4.

---

## Step 4 — Frontend: Implement the module

**Delegate to: FE-VUE-LARAVEL--frontend**

Context to pass: module name and pointer to the backend API (e.g. `docs/API-REFERENCE.md`). Instruct the subagent to implement the frontend using the documented API and to follow: `restaurant-management-system.mdc` (models in `frontend/src/models/`, services in `frontend/src/services/`, no raw API in components), `api-reference-frontend.mdc`, `frontend-forms-validation.mdc` (validate in JS, `novalidate` on forms), `frontend-mobile-first.mdc`. When the subagent finishes, proceed to Step 5.

---

## Step 5 — Frontend + UI/UX: Review

**Delegate to: DESIGNER-WEB-UXUI**

Context to pass: the frontend changes from Step 4. Instruct the subagent to check against `api-reference-frontend.mdc`, `frontend-forms-validation.mdc`, `frontend-mobile-first.mdc`, and `restaurant-management-system.mdc`, and to output either **PASS** or a clear list of **issues**.  
- **If PASS:** Proceed to Step 6.  
- **If issues:** Hand the issues back to **FE-VUE-LARAVEL--frontend** to fix, then run **DESIGNER-WEB-UXUI** again (repeat until PASS), then proceed to Step 6.

---

## Step 6 — E2E tests

**Delegate to: TESTER-e2e-playwright**

Context to pass: module name and summary of the feature (backend + frontend). Instruct the subagent to add or update E2E tests for the main user flows (e.g. owner creates/edits the resource, public view if applicable), run `npm run test:e2e` from `frontend/`, and fix until tests pass. When the subagent finishes, proceed to Step 7.

---

## Step 7 — Definition of done

- Summarize what was built (backend, frontend, tests).
- **Ask the user** whether they consider the feature complete. Do not mark the feature as finished without their confirmation.

---

## Task progress checklist

Use this to track where you are:

```
- [ ] Step 1: BE-LARAVEL--architect (backend implementation)
- [ ] Step 2: REVIEWER-senior-laravel-backend-architect (repeat until PASS)
- [ ] Step 3: BE-LARAVEL--architect (BE unit tests)
- [ ] Step 4: FE-VUE-LARAVEL--frontend
- [ ] Step 5: DESIGNER-WEB-UXUI (repeat until PASS)
- [ ] Step 6: TESTER-e2e-playwright
- [ ] Step 7: User confirmation (you)
```

---

## Extracting the module name

From the user message (e.g. "Develop the menu items module", "implement the combos module"), identify the **module name** and use it consistently for routes, models, services, and tests (e.g. "menu items" → menu items API, menu item model, menu items view, menu items E2E).
