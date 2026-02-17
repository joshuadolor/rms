/** Reserved subdomains: never treat as restaurant slug. */
const RESERVED_SUBDOMAINS = new Set(['www', 'app', 'api', 'admin'])

/**
 * If the current host is a restaurant subdomain, return the slug; otherwise null.
 * Supports: *.localhost and *.VITE_APP_PUBLIC_DOMAIN (e.g. test.rms.local).
 */
export function getSubdomainSlug() {
  if (typeof window === 'undefined') return null
  const host = window.location.hostname
  const parts = host.split('.')
  if (parts.length < 2) return null
  const first = parts[0].toLowerCase()
  if (RESERVED_SUBDOMAINS.has(first) || !first) return null
  const publicDomain = import.meta.env.VITE_APP_PUBLIC_DOMAIN ?? ''
  if (publicDomain) {
    const domain = publicDomain.split(':')[0].toLowerCase()
    const suffix = parts.slice(1).join('.')
    if (suffix === domain) return first
  }
  if (parts[parts.length - 1] === 'localhost') return first
  return null
}
