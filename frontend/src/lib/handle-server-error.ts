import i18n from '@/i18n'
import { errorStatus, isApiError, serverMessage } from '@/lib/api'
import { toast } from '@/lib/toast'

/** i18n key for a failed request, by HTTP status. */
export function errorKey(error: unknown): string {
  if (!isApiError(error)) return 'errors.generic'
  switch (errorStatus(error)) {
    case 0:
      return 'errors.network'
    case 402:
      return 'errors.read_only'
    case 403:
      return 'errors.forbidden'
    case 404:
      return 'errors.not_found'
    case 409:
      return 'errors.in_progress'
    case 429:
      return 'errors.too_many'
    default:
      return 'errors.generic'
  }
}

/**
 * Shows one toast for a failed request: the server's own (translated)
 * message when it sent one, otherwise a message for the status code.
 * 5xx messages are never shown as-is (they may be technical).
 */
export function handleServerError(error: unknown) {
  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.log(error)
  }

  const status = errorStatus(error)
  const message = status > 0 && status < 500 ? serverMessage(error) : null

  // Plan limit reached: a button to the contact from platform settings.
  const up = (error as { response?: { data?: { upgrade?: Upgrade } } }).response
    ?.data?.upgrade
  const href = up?.whatsapp || (up?.email && `mailto:${up.email}`)
  const text = message ?? i18n.t(errorKey(error))
  if (!href) return toast.error(text)
  toast.error(text, [
    i18n.t(up?.whatsapp ? 'upgrade.whatsapp' : 'upgrade.email'),
    href,
  ])
}

type Upgrade = { whatsapp: string | null; email: string | null }
