import { useEffect, useState } from 'react'

const params = (url: string) =>
  new URLSearchParams(url.split(/[?#]/).slice(1).join('&'))

/**
 * The address the preview frame shows: a change of one of `instant` (a menu
 * style, a colour mode…) shows at once; anything else (typing a colour)
 * waits for a pause, so the frame doesn't reload on every key.
 */
export function usePreviewSrc(url: string, instant: readonly string[]) {
  const [src, setSrc] = useState(url)
  const [loading, setLoading] = useState(true)
  useEffect(() => {
    if (url === src) return
    const before = params(src)
    const after = params(url)
    const now = instant.some((k) => before.get(k) !== after.get(k))
    const timer = setTimeout(
      () => {
        setLoading(true)
        setSrc(url)
      },
      now ? 0 : 350
    )
    return () => clearTimeout(timer)
  }, [url, src, instant])
  return { src, loading, onLoad: () => setLoading(false) }
}
