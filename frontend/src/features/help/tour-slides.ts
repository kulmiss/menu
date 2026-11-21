import { type Role } from '@/features/auth/api'

type Slide = { key: string; shot: string; to: string }

/**
 * The welcome tour (backend QUESTIONS #195): at most 4 short slides on the
 * first sign-in — your dashboard, add your first dish, choose your menu
 * look, share your QR code. Skippable; reopened from Help. Slides for jobs
 * the person can't do (role, switched-off feature) are left out.
 */
export function tourSlides(role: Role | null, soldOut: boolean): Slide[] {
  const manager = role === 'owner' || role === 'manager'
  return [
    { key: 'dashboard', shot: 'dashboard', to: '/admin' },
    manager
      ? { key: 'dish', shot: 'dish-editor', to: '/admin/menu' }
      : soldOut
        ? { key: 'sold_out', shot: 'dishes', to: '/admin/dishes' }
        : null,
    manager
      ? { key: 'look', shot: 'appearance', to: '/admin/appearance' }
      : null,
    manager ? { key: 'qr', shot: 'qr-codes', to: '/admin/qr-codes' } : null,
  ].filter((s): s is Slide => s !== null)
}
