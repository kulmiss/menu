/**
 * Only same-site paths are allowed after sign-in (`?redirect=`), so a link
 * like `/auth/sign-in?redirect=https://evil.example` cannot send people away.
 */
export function safeRedirect(
  target: string | undefined | null,
  fallback = '/admin'
): string {
  if (!target) return fallback
  let path: string
  try {
    // Accept full URLs on our own origin (router hrefs) and keep their path.
    const url = new URL(target, window.location.origin)
    if (url.origin !== window.location.origin) return fallback
    path = url.pathname + url.search + url.hash
  } catch {
    return fallback
  }
  if (!path.startsWith('/') || path.startsWith('//')) return fallback
  // Never back to a sign-in form; an invitation page is fine.
  if (path.startsWith('/auth/') && !path.startsWith('/auth/invite/')) {
    return fallback
  }
  return path
}
