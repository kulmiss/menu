import { type QueryClient } from '@tanstack/react-query'
import { redirect } from '@tanstack/react-router'
import { errorStatus } from '@/lib/api'
import { meQueryOptions, type Me } from './api'

/**
 * Loads the signed-in user before any /admin page renders. Not signed in
 * (401) → the sign-in page, which brings the user back here afterwards.
 */
export async function requireMe(
  queryClient: QueryClient,
  href: string
): Promise<Me> {
  try {
    return await queryClient.ensureQueryData(meQueryOptions)
  } catch (error) {
    if (errorStatus(error) === 401) {
      throw redirect({ to: '/auth/sign-in', search: { redirect: href } })
    }
    throw error
  }
}
