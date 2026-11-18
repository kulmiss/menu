// Every guest-menu style's stylesheet (backend/resources/public-menu/style-*.css),
// keyed by style name, so the guest-page tests cover a new style automatically.
const files = import.meta.glob<string>('@public-menu/style-*.css', {
  query: '?raw',
  import: 'default',
  eager: true,
})

export const GUEST_STYLES: Record<string, string> = Object.fromEntries(
  Object.entries(files).map(([path, css]) => [
    path.replace(/^.*style-(\w+)\.css$/, '$1'),
    css,
  ])
)
