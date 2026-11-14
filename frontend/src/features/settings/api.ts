import { api } from '@/lib/api'

export const updateProfile = (v: {
  name: string
  email: string
  locale: string
  /** Needed only when the e-mail changes (SECURITY_REPORT.md H3). */
  current_password?: string
}) => api.put('/auth/user/profile-information', v)

export const updatePassword = (v: {
  current_password: string
  password: string
  password_confirmation: string
}) => api.put('/auth/user/password', v)

/** Fortify asks for the password again before 2FA changes (valid ~3 hours). */
export const confirmPassword = (password: string) =>
  api.post('/auth/user/confirm-password', { password })

export const enableTwoFactor = () =>
  api.post('/auth/user/two-factor-authentication')
export const disableTwoFactor = () =>
  api.delete('/auth/user/two-factor-authentication')
export const confirmTwoFactor = (code: string) =>
  api.post('/auth/user/confirmed-two-factor-authentication', { code })

export const twoFactorQr = async () =>
  (await api.get<{ svg: string; url: string }>('/auth/user/two-factor-qr-code'))
    .data
export const twoFactorSecret = async () =>
  (await api.get<{ secretKey: string }>('/auth/user/two-factor-secret-key'))
    .data.secretKey
export const recoveryCodes = async () =>
  (await api.get<string[]>('/auth/user/two-factor-recovery-codes')).data
export const regenerateRecoveryCodes = () =>
  api.post('/auth/user/two-factor-recovery-codes')
