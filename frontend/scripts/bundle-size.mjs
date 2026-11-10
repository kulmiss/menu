// Admin bundle budget (PLAN.md §15): the JS + CSS a browser downloads to show
// /admin the first time — the entry, everything it imports up front, and the
// lazily loaded /admin layout and dashboard routes — gzipped.
// Usage: node scripts/bundle-size.mjs [--budget-kb=150]
import { readFileSync } from 'node:fs'
import { gzipSync } from 'node:zlib'
import { join } from 'node:path'

const OUT = new URL('../../backend/public/app/', import.meta.url).pathname
const budgetKb = Number(process.argv.find((a) => a.startsWith('--budget-kb='))?.split('=')[1] ?? 150)
const manifest = JSON.parse(readFileSync(join(OUT, '.vite/manifest.json'), 'utf8'))

const entryKey = Object.keys(manifest).find((k) => manifest[k].isEntry)
// Route chunks for the first admin screen.
const routeKeys = Object.keys(manifest).filter((k) =>
  /src\/routes\/admin\/(route|index)\.tsx\?tsr-split/.test(k)
)

const files = new Set()
const visit = (key, includeDynamic = false) => {
  const chunk = manifest[key]
  if (!chunk || files.has(chunk.file)) return
  files.add(chunk.file)
  for (const css of chunk.css ?? []) files.add(css)
  for (const dep of chunk.imports ?? []) visit(dep)
  if (includeDynamic) for (const dep of chunk.dynamicImports ?? []) visit(dep)
}
visit(entryKey)
for (const key of routeKeys) visit(key)

let total = 0
const rows = []
for (const file of files) {
  const size = gzipSync(readFileSync(join(OUT, file)), { level: 9 }).length
  total += size
  rows.push([size, file])
}
rows.sort((a, b) => b[0] - a[0])
for (const [size, file] of rows.slice(0, Number(process.env.TOP ?? 12))) console.log(`${(size / 1024).toFixed(1).padStart(7)} KB  ${file}`)
console.log(`\nAdmin first load (gzip): ${(total / 1024).toFixed(1)} KB in ${files.size} files — budget ${budgetKb} KB`)
if (routeKeys.length === 0) {
  console.error('Could not find the /admin route chunks in the manifest.')
  process.exit(1)
}
if (total > budgetKb * 1024) {
  console.error('Over budget.')
  process.exit(1)
}
