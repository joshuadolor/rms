# Restaurant Management System — Agent Prompt

## Product Vision

Build a **free-to-use** Restaurant Management System that lets owners manage one or more restaurants, publish menus via public links and QR codes, and control availability (menu items, opening hours, siesta-style breaks).

---

## Core Requirements

### 1. Free for Restaurant Owners
- The system must be free for restaurant owners to use. No paywall for core features.
- **Free tier:** One restaurant per owner (one subdomain). Multiple restaurants / extra subdomains are for a paid tier (to be defined later).

### 2. Multi-Restaurant Management
- Each owner can manage **one or more** restaurants from a single account (one on free tier; multiple when paid tier exists).
- Owners can create, edit, and delete restaurants and their settings.

### 3. Public Menu Access
- **URL structure:** All public or generic restaurant pages use **subdomains** (e.g. `{restaurant-slug}.yourapp.com`). Do not use path-based public URLs for restaurant menus or pages.
- **Generic public link:** Every restaurant gets a stable public URL on a subdomain that always shows that restaurant’s current menu (e.g. `la-trattoria.yourapp.com`).
- **QR codes:** Every menu has an associated QR code that points to the restaurant’s menu (or optionally to the restaurant’s main page—see next point).
- **QR destination option:** Restaurant owners can choose whether the QR code opens:
  - **A)** The menu directly, or  
  - **B)** The restaurant’s main/page first (then user can go to menu).

### 4. Availability & Scheduling
- **Menu item availability:** Support availability rules for individual menu items (e.g. only show at certain times or on certain days).
- **Opening and closing hours:** Support configurable opening and closing times per restaurant (e.g. 12:00–15:00, 19:00–23:00).
- **Siesta / split hours:** Support split schedules (e.g. Spain-style siesta: open morning, closed midday, open evening). No assumption of a single continuous block of hours.

### 5. Menu Copy Between Restaurants
- Owners with **two or more** restaurants can **copy a menu** from one restaurant to another (full or selected items), to avoid re-entering the same menu. When copying, include combo items and resolve their references to menu items in the target restaurant.

### 6. Website Templates
- Provide **exactly three** generic website/menu templates that restaurant owners can choose from for their public menu or restaurant page.
- Templates should be distinct (e.g. layout, style, or structure) so owners have a real choice.

### 7. Menu Management
- **Structure:** A menu has **categories** (e.g. Starters, Mains, Desserts); each category contains **menu items**. Combos are a special type of menu item that reference other items.
- **Draggable order:** Both **menu categories** and **menu items** are draggable. Owners can reorder categories (e.g. Starters → Mains → Desserts) and reorder items within each category. Persist order and reflect it on the public menu.
- **Combo items:** Owners can create **combo items** — a menu item that is a combination of existing menu items (e.g. "Burger + Fries + Drink"). The combo has its own name, optional price, and references one or more menu items. Combo items appear on the menu like other items and can have availability rules.

---

## Constraints & Conventions

- **Public / generic sites = subdomain:** Any public or generic restaurant site (menu, restaurant page, etc.) must be served on a subdomain (e.g. `{restaurant-slug}.yourapp.com`), not under a path on the main domain.
- Prioritize clarity and simplicity for restaurant owners (non-technical users).
- Public menu pages must work well on mobile (QR codes are often scanned on phones).
- **Testing:** For every feature built, add **e2e tests** that cover the main user flows (e.g. owner creates restaurant, adds menu, visits public subdomain; guest scans QR and sees menu).
- **Definition of done:** Before treating a feature as finished, **ask me first** whether I consider it complete. Do not assume a feature is done without my confirmation.
- Keep the scope to the features above unless the user explicitly asks for more.

---

## Success Criteria

- [ ] Owners can use the system for free.
- [ ] Owners can manage multiple restaurants and assign each a public link.
- [ ] Each restaurant has a public subdomain URL and menu-specific QR code(s), with configurable QR destination (menu vs restaurant page).
- [ ] Availability is supported for menu items and for opening/closing hours, including split (siesta) schedules.
- [ ] Owners can copy a menu from one restaurant to another.
- [ ] Three selectable website templates are available for the public menu/page.
- [ ] Menu categories and menu items are draggable; order is saved and shown on the public menu.
- [ ] Owners can create combo items (a menu item that combines other menu items).

---

## Post-MVP / Future Scope (for AI context)

- **Do not build these in the MVP.** Once the MVP above is done, we plan to add other features (e.g. **table reservation** and similar). Those future features will be **for paid users only**.
- Keep this in mind when making architecture or data-model choices (e.g. leave room for paid tiers and feature gating), but do not implement paid-only features until explicitly requested.
