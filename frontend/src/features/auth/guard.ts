import { type QueryClient } from '@tanstack/react-query'
import { redirect } from '@tanstack/react-router'
import { errorStatus } from '@/lib/api'
import { signInUrl } from '@/lib/area'
import { meQueryOptions, type Me } from './api'

/**
 * Loads the signed-in user before any page renders. Not signed in (401) →
 * the area's sign-in page (a server page), which brings the user back here.
 */
export async function requireMe(
  queryClient: QueryClient,
  href: string
): Promise<Me> {
  try {
    return await queryClient.ensureQueryData(meQueryOptions)
  } catch (error) {
    if (errorStatus(error) === 401) {
      throw redirect({ href: signInUrl(href), reloadDocument: true })
    }
    throw error
  }
}
