# Restaurant subdomains: [slug].domain.com

Each restaurant gets a public URL as soon as it’s created: **`[slug].domain.com`** (e.g. `pizza-place.localhost` in dev, `pizza-place.menus.example.com` in production). No per-restaurant DNS or nginx config is required.

## How it works

1. **Slug = subdomain**  
   The restaurant’s `slug` (unique, set on create) is the subdomain. The API returns `public_url` on every restaurant payload (e.g. `https://pizza-place.menus.example.com`).

2. **Wildcard DNS**  
   One DNS record covers all subdomains:
   - **A record:** `*.menus.example.com` → your server’s IP  
   - **Or CNAME:** `*.menus.example.com` → `your-server.com`  

   Without this, only explicitly defined subdomains resolve. With it, any `[slug].menus.example.com` resolves to the same server.

3. **Nginx**  
   Subdomain requests (e.g. `test.rms.local`) must be sent to **Laravel**, which serves the Blade public page. The main domain (e.g. `rms.local`) is served by the frontend (Vue). In dev, `docker/nginx/default.conf` has a server block for `*.rms.local` that proxies to the API so **http://test.rms.local** returns the Blade template.

## Local dev (Docker)

- **Nginx** (`docker/nginx/default.conf`): `server_name *.localhost localhost;`  
  Browsers resolve `*.localhost` to `127.0.0.1`, so no `/etc/hosts` entries are needed.
- **API**: `RESTAURANT_DOMAIN=localhost` (set in docker-compose or `.env`) so `public_url` is `http://<slug>.localhost`.

### How to test the generic restaurant page (subdomain)

1. **Set the root domain in the frontend**  
   In `frontend/.env` set:
   ```env
   VITE_APP_PUBLIC_DOMAIN=rms.local
   ```
   (Use `localhost` if you only use `*.localhost`; use your production domain in prod.)

2. **Make subdomains resolve**
   - **\*.localhost**  
     Browsers already resolve `pizza.localhost` to 127.0.0.1. No extra setup. Open **`http://pizza.localhost`** (with nginx on port 80).
   - **\*.rms.local**  
     Add entries in `/etc/hosts` (no wildcard on most OSes), e.g.:
     ```text
     127.0.0.1 rms.local
     127.0.0.1 pizza.rms.local
     127.0.0.1 myplace.rms.local
     ```
     Or use **dnsmasq** with a wildcard for `.rms.local` so any `*.rms.local` resolves.

3. **Nginx**  
   `docker/nginx/default.conf` already has `server_name *.localhost localhost *.rms.local rms.local`. Recreate nginx if you changed it: `docker compose up -d --force-recreate nginx`.

4. **API (optional)**  
   In `api/.env` set `RESTAURANT_DOMAIN=rms.local` (or your domain) so the API returns `public_url` like `http://pizza.rms.local` on restaurant payloads.

5. **Open the subdomain**  
   **`http://test.rms.local`** (with `test.rms.local` in hosts and nginx routing `*.rms.local` to Laravel). Laravel serves the **Blade** public page (template-1 or template-2). Use a restaurant slug that exists (e.g. slug `test`).

6. **Fallback by path**  
   **`http://rms.local/r/<slug>`** still works without subdomain DNS (e.g. `http://rms.local/r/pizza`).

7. **Public API**  
   `GET /api/public/restaurants/<slug>?locale=en` returns the restaurant’s public data. No auth.

## Production checklist

1. **DNS**  
   Add a wildcard A or CNAME for your restaurant domain, e.g. `*.menus.example.com` → your server.

2. **Nginx**  
   Route **subdomains** (e.g. `pizza.menus.example.com`) to Laravel so the Blade public page is served. Route the **main domain** (e.g. `menus.example.com`) to the frontend and `/api` to Laravel. See `docker/nginx/default.conf`: one server block for `*.rms.local` → API; one for `rms.local` → frontend + `/api` to API.

3. **API env**  
   Set `RESTAURANT_DOMAIN=menus.example.com` (no scheme, no subdomain). The API will then return `public_url` like `https://pizza-place.menus.example.com` (scheme from the request).

4. **Frontend**  
   Use the subdomain from `window.location.hostname` to determine the restaurant slug when the user is on `[slug].domain.com`, and load that restaurant’s public page (or show 404 if slug doesn’t exist).

## Is it possible without wildcard DNS?

No. You need either:
- **Wildcard DNS** (recommended): one record `*.domain.com` → server, and nginx accepts `*.domain.com`, or  
- **Per-restaurant DNS**: create an A/CNAME for each slug (e.g. `pizza.menus.example.com`, `cafe.menus.example.com`). That doesn’t scale and isn’t implemented here; we rely on a single wildcard.
