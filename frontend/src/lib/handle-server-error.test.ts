import { AxiosError } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { errorKey, handleServerError } from './handle-server-error'

const toastError = vi.hoisted(() => vi.fn())

vi.mock('sonner', () => ({ toast: { error: toastError } }))

function axiosError(status: number, data: unknown = {}) {
  const error = new AxiosError('Request failed')
  error.response = { status, data } as AxiosError['response']
  return error
}

beforeEach(() => toastError.mockClear())

describe('handleServerError', () => {
  it('shows a network message when there is no response', () => {
    handleServerError(new AxiosError('Network Error'))
    expect(toastError).toHaveBeenCalledWith(
      'No connection. Check your internet and try again.'
    )
  })

  it("shows the server's message for client errors", () => {
    handleServerError(axiosError(422, { message: 'The slug is taken.' }))
    expect(toastError).toHaveBeenCalledWith('The slug is taken.')
  })

  it('never shows raw server messages for 5xx errors', () => {
    handleServerError(axiosError(500, { message: 'SQLSTATE[42S02]' }))
    expect(toastError).toHaveBeenCalledWith(
      'Something went wrong. Please try again.'
    )
  })

  it('maps status codes to messages', () => {
    expect(errorKey(axiosError(402))).toBe('errors.read_only')
    expect(errorKey(axiosError(403))).toBe('errors.forbidden')
    expect(errorKey(axiosError(409))).toBe('errors.in_progress')
    expect(errorKey(axiosError(429))).toBe('errors.too_many')
    expect(errorKey(new AxiosError('Network Error'))).toBe('errors.network')
    expect(errorKey(new Error('x'))).toBe('errors.generic')
  })
})
