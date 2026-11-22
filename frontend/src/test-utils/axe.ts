import axe from 'axe-core'

/** Accessibility problems axe finds inside `el` (empty = none), one line each. */
export async function axeViolations(el: Element): Promise<string[]> {
  const result = await axe.run(el, { resultTypes: ['violations'] })
  return result.violations.map(
    (v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`
  )
}
