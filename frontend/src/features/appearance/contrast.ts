/**
 * WCAG contrast, same maths as the server (App\Support\Contrast), so the
 * screen can warn while the owner picks a colour.
 */
export const MIN_CONTRAST = 4.5

export const isHex = (v: string) => /^#[0-9a-f]{6}$/i.test(v)

function channel(v: number) {
  const s = v / 255
  return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
}

export function luminance(hex: string) {
  const n = parseInt(hex.slice(1), 16)
  const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255]
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}

export function contrast(a: string, b: string) {
  const [x, y] = [luminance(a), luminance(b)]
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
}

/** True when the colour can't be read as text on a white page. */
export const tooLightOnWhite = (hex: string) =>
  isHex(hex) && contrast(hex, '#ffffff') < MIN_CONTRAST
