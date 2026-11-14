import { describe, expect, it, vi } from 'vitest'
import { toast, toasterMounted } from './toast'

const sonnerError = vi.hoisted(() => vi.fn())
vi.mock('sonner', () => ({ toast: { error: sonnerError } }))

describe('toast', () => {
  it('adds a link button when asked', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null)
    toast.error('Limit reached.', ['Upgrade on WhatsApp', 'https://wa.me/1'])
    toasterMounted()
    await vi.waitFor(() => expect(sonnerError).toHaveBeenCalled())
    const [text, options] = sonnerError.mock.calls[0]
    expect(text).toBe('Limit reached.')
    expect(options.action.label).toBe('Upgrade on WhatsApp')
    options.action.onClick()
    expect(open).toHaveBeenCalledWith('https://wa.me/1', '_blank')
    open.mockRestore()
  })
})
