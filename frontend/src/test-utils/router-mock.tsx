import { vi } from 'vitest'

/** Shared spy for `useNavigate()` in mocked router modules. */
export const navigate = vi.fn(async () => {})

/**
 * Router module replacement for component tests: plain links, a spy for
 * navigation. Use as
 *   vi.mock('@tanstack/react-router', async (orig) => routerMock(await orig()))
 */
export function routerMock(actual: Record<string, unknown>) {
  return {
    ...actual,
    useNavigate: () => navigate,
    Link: ({
      children,
      to,
      search,
      className,
    }: {
      children?: React.ReactNode
      to: string
      search?: Record<string, string | undefined>
      className?: string
    }) => {
      const query = new URLSearchParams(
        Object.entries(search ?? {}).filter(
          (e): e is [string, string] => !!e[1]
        )
      ).toString()
      return (
        <a href={query ? `${to}?${query}` : to} className={className}>
          {children}
        </a>
      )
    },
  }
}
