/**
 * The two areas (QUESTIONS #215–#222). Laravel serves this app at /admin
 * (restaurant teams) and at the Super Admin's own address, which only appears
 * in the HTML of that area (`<meta name="super-path">`), never in the
 * restaurant one. Inside the app, Super Admin screens keep their route ids
 * under /platform; the router rewrites them to and from the real address.
 */
export const PLATFORM = '/platform'

/** "/office-7k2q" in the Super Admin area, null in the restaurant area. */
export function superPath(): string | null {
  const meta = document
    .querySelector('meta[name="super-path"]')
    ?.getAttribute('content')
  if (meta && /^\/[a-z0-9_-]+$/.test(meta)) return meta
  // The Vite dev server has no Laravel page around it.
  return import.meta.env.DEV ? '/super' : null
}

const under = (pathname: string, prefix: string) =>
  pathname === prefix || pathname.startsWith(prefix + '/')

/** Browser address → route id: /<super>/x → /platform/x; a typed /platform/x is not a page. */
export function rewriteIn(pathname: string, path = superPath()): string {
  if (path && under(pathname, path))
    return PLATFORM + pathname.slice(path.length)
  if (under(pathname, PLATFORM)) return '/not-found' + pathname
  return pathname
}

/** Route id → browser address: /platform/x → /<super>/x. */
export function rewriteOut(pathname: string, path = superPath()): string {
  if (path && under(pathname, PLATFORM))
    return path + pathname.slice(PLATFORM.length)
  return pathname
}

/**
 * The sign-in page of the area the app was opened in (a server page, so a
 * full page load). `expired`: the page says the session ended.
 */
export function signInUrl(redirect?: string, expired = false): string {
  const path = superPath()
  const base = path && !import.meta.env.DEV ? `${path}/sign-in` : '/signin'
  const query = new URLSearchParams()
  if (redirect) query.set('redirect', redirect)
  if (expired) query.set('expired', '1')
  const qs = query.toString()
  return qs ? `${base}?${qs}` : base
}
