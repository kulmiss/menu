/**
 * Menu address from a restaurant name: "Buna & Bread Café" → "buna-bread-cafe".
 * Same rules as the server (App\Rules\ValidSlug): 3–50 of a–z, 0–9 and single
 * hyphens. Names in other scripts give "" — the owner then types one.
 */
export function slugify(name: string): string {
  return name
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 50)
    .replace(/-+$/g, '')
}

export const SLUG_PATTERN = /^[a-z0-9](?:[a-z0-9-]{1,48})[a-z0-9]$/

export function isValidSlug(slug: string): boolean {
  return SLUG_PATTERN.test(slug) && !slug.includes('--')
}
