---
name: develop-frontend
description: Runs the frontend pipeline for a module: designer/UX review first, then frontend implementation, then E2E tests. Use when the user says "check out this module", "develop the frontend for this module", or similar; delegates to DESIGNER-WEB-UXUI, FE-VUE-LARAVEL--frontend, and TESTER-e2e-playwright in order.
---

# Develop Frontend — Designer → Dev → E2E

Run this workflow when the user asks to **check out this module** or to develop the frontend for a module. **Delegate each step to the corresponding subagent**; do not perform the step yourself. Execute steps in order. **All project rules remain in effect for every subagent** — ensure subagents are instructed to follow them.

## Subagents used

The subagents are **globally declared** (user-level). Use these exact names when delegating:

| Step | Subagent name (global) | Use for |
|------|------------------------|--------|
| 1 | **DESIGNER-WEB-UXUI** | Review or design the UI/UX for the module first |
| 2 | **FE-VUE-LARAVEL--frontend** | Implement the frontend for the module |
| 3 | **TESTER-e2e-playwright** | Add/run E2E tests for the module |

When delegating, pass this project's rules and context so the subagent follows them for this repo.

**Orchestration:** You are the parent. For each step, spawn the subagent by the name above with the context below. If the designer returns issues or recommendations: pass them to **FE-VUE-LARAVEL--frontend** so they are addressed during implementation. Step 4 (definition of done) you do yourself.

## Rules that always apply

While following this skill, every project rule still applies:

- **`.cursor/rules/restaurant-management-system.mdc`** — API modeling, services layer, models in `frontend/src/models/`, services in `frontend/src/services/`, E2E expectations, mobile-first.
- **`.cursor/rules/api-reference-frontend.mdc`** — Use `docs/API-REFERENCE.md` for all API calls; match request/response shapes and error handling.
- **`.cursor/rules/frontend-forms-validation.mdc`** — Frontend validate before API; no native HTML validation; `novalidate` on forms; run `npm run test:e2e` before considering form workflows done.
- **`.cursor/rules/frontend-mobile-first.mdc`** — Mobile-first layout and touch targets for all frontend work.

If any step would violate a rule, correct the approach so it complies.

---

## Pipeline overview

```
Designer (UX/UI) → Frontend Dev → E2E Tests → Done (ask user for confirmation)
```

---

## Step 1 — Designer / UX–UI

**Delegate to: DESIGNER-WEB-UXUI**

Context to pass: module name and what the module is (e.g. menu items, restaurant settings). Ask the subagent to review the current state of the module (or existing designs) and output either **PASS** or a clear list of **design/UX recommendations** (layout, hierarchy, typography, touch targets, accessibility). If there are recommendations, include them in the handoff to Step 2 so the frontend dev can implement them. When the subagent finishes, proceed to Step 2.

---

## Step 2 — Frontend: Implement the module

**Delegate to: FE-VUE-LARAVEL--frontend**

Context to pass: module name, pointer to `docs/API-REFERENCE.md`, and any design/UX recommendations from Step 1. Instruct the subagent to implement (or update) the frontend using the documented API and to follow: `restaurant-management-system.mdc` (models, services, no raw API in components), `api-reference-frontend.mdc`, `frontend-forms-validation.mdc` (validate in JS, `novalidate` on forms), `frontend-mobile-first.mdc`. When the subagent finishes, proceed to Step 3.

---

## Step 3 — E2E tests

**Delegate to: TESTER-e2e-playwright**

Context to pass: module name and summary of the frontend feature. Instruct the subagent to add or update E2E tests for the main user flows (e.g. owner creates/edits the resource, public view if applicable), run `npm run test:e2e` from `frontend/`, and fix until tests pass. When the subagent finishes, proceed to Step 4.

---

## Step 4 — Definition of done

- Summarize what was done (design input, frontend implementation, E2E coverage).
- **Ask the user** whether they consider the frontend for this module complete. Do not mark it finished without their confirmation.

---

## Task progress checklist

```
- [ ] Step 1: DESIGNER-WEB-UXUI (design/UX review or recommendations)
- [ ] Step 2: FE-VUE-LARAVEL--frontend (implementation)
- [ ] Step 3: TESTER-e2e-playwright (E2E tests)
- [ ] Step 4: User confirmation (you)
```

---

## Identifying the module

From the user message (e.g. "check out this module", "develop the frontend for the restaurants module") or from current context (open files, recent changes), identify the **module name** and use it consistently in handoffs to subagents (e.g. "menu items", "restaurants", "combos").
