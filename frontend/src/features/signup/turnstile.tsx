import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'

/**
 * Cloudflare Turnstile check (optional, Super Admin → Settings → Sign-up).
 * The script downloads only when the check is switched on.
 */
type TurnstileApi = {
  render: (
    el: HTMLElement,
    options: {
      sitekey: string
      callback: (token: string) => void
      'expired-callback': () => void
      'error-callback': () => void
      theme?: 'auto'
    }
  ) => string
  reset: (id: string) => void
  remove: (id: string) => void
}

declare global {
  interface Window {
    turnstile?: TurnstileApi
  }
}

const SRC =
  'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
let loading: Promise<TurnstileApi> | null = null

function loadTurnstile(): Promise<TurnstileApi> {
  if (window.turnstile) return Promise.resolve(window.turnstile)
  if (!loading) {
    loading = new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = SRC
      script.async = true
      script.onload = () =>
        window.turnstile ? resolve(window.turnstile) : reject(new Error())
      script.onerror = () => {
        loading = null
        reject(new Error('turnstile'))
      }
      document.head.appendChild(script)
    })
  }
  return loading
}

type Props = {
  siteKey: string
  onToken: (token: string) => void
  /** Changes after a failed submit: a used token cannot be sent twice. */
  resetKey: number
}

export function Turnstile({ siteKey, onToken, resetKey }: Props) {
  const { t } = useTranslation()
  const box = useRef<HTMLDivElement>(null)
  const widget = useRef<string | null>(null)

  useEffect(() => {
    let cancelled = false
    void loadTurnstile()
      .then((ts) => {
        if (cancelled || !box.current) return
        box.current.textContent = ''
        widget.current = ts.render(box.current, {
          sitekey: siteKey,
          theme: 'auto',
          callback: (token) => onToken(token),
          'expired-callback': () => onToken(''),
          'error-callback': () => onToken(''),
        })
      })
      .catch(() => {})
    return () => {
      cancelled = true
      if (widget.current) window.turnstile?.remove(widget.current)
      widget.current = null
    }
  }, [siteKey, onToken])

  useEffect(() => {
    if (resetKey > 0 && widget.current) {
      window.turnstile?.reset(widget.current)
      onToken('')
    }
  }, [resetKey, onToken])

  return (
    <div ref={box} className='min-h-[65px]' data-testid='turnstile'>
      <span className='text-sm text-muted-foreground'>
        {t('signup.captcha_loading')}
      </span>
    </div>
  )
}
