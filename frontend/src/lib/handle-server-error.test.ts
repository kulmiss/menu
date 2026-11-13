import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, type SentRequest } from '@/lib/api'
import { errorKey, handleServerError } from './handle-server-error'

const toastError = vi.hoisted(() => vi.fn())

vi.mock('@/lib/toast', () => ({ toast: { error: toastError } }))

const request = { method: 'GET', url: '/x' } as SentRequest

function apiError(status: number, data: unknown = {}) {
  return new ApiError('Request failed', request, { status, data, headers: {} })
}

beforeEach(() => toastError.mockClear())

describe('handleServerError', () => {
  it('shows a network message when there is no response', () => {
    handleServerError(new ApiError('Network Error', request))
    expect(toastError).toHaveBeenCalledWith(
      'No connection. Check your internet and try again.'
    )
  })

  it("shows the server's message for client errors", () => {
    handleServerError(apiError(422, { message: 'The slug is taken.' }))
    expect(toastError).toHaveBeenCalledWith('The slug is taken.')
  })

  it('never shows raw server messages for 5xx errors', () => {
    handleServerError(apiError(500, { message: 'SQLSTATE[42S02]' }))
    expect(toastError).toHaveBeenCalledWith(
      'Something went wrong. Please try again.'
    )
  })

  it('maps status codes to messages', () => {
    expect(errorKey(apiError(402))).toBe('errors.read_only')
    expect(errorKey(apiError(403))).toBe('errors.forbidden')
    expect(errorKey(apiError(409))).toBe('errors.in_progress')
    expect(errorKey(apiError(429))).toBe('errors.too_many')
    expect(errorKey(new ApiError('Network Error', request))).toBe(
      'errors.network'
    )
    expect(errorKey(new Error('x'))).toBe('errors.generic')
  })
})
