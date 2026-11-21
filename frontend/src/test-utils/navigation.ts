/**
 * Catches full-page navigations (location.assign to the server's sign-in
 * pages) so a browser test stays on its page; `urls` lists where it would go.
 */
export function catchNavigation() {
  const urls: string[] = []
  const nav = (window as unknown as { navigation: EventTarget }).navigation
  const stop = (e: Event) => {
    e.preventDefault()
    const url = new URL(
      (e as unknown as { destination: { url: string } }).destination.url
    )
    urls.push(url.pathname + url.search)
  }
  nav.addEventListener('navigate', stop)
  return { urls, restore: () => nav.removeEventListener('navigate', stop) }
}
