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

// The header drawings of the styles M–Q (backend/resources/public-menu/art/*.svg).
const art = import.meta.glob<string>('@public-menu/art/*.svg', {
  query: '?raw',
  import: 'default',
  eager: true,
})

export const GUEST_ART: Record<string, string> = Object.fromEntries(
  Object.entries(art).map(([path, svg]) => [
    path.replace(/^.*\/(\w+)\.svg$/, '$1'),
    svg,
  ])
)
