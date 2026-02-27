# Nginx for RMS dev

`default.conf` is **mounted** from the host. After changing it: `docker compose restart nginx`.

**Public restaurant page:** Open **http://test.rms.local** (port 80 or 8080). The Vue app redirects to `/r/test`, and nginx (or Vite proxy) forwards `/r/*` to Laravel, which returns the Blade page. One server block; no subdomain routing needed.
