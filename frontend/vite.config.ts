/// <reference types="vitest/config" />
import path from 'path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import { playwright } from '@vitest/browser-playwright'
import { readdirSync, readFileSync } from 'fs'

// `_status` of every translation ("machine_draft" until reviewed).
const localeStatus = Object.fromEntries(
  readdirSync(path.resolve(__dirname, 'src/locales')).map((code) => [
    code,
    JSON.parse(
      readFileSync(
        path.resolve(__dirname, `src/locales/${code}/common.json`),
        'utf8'
      )
    )._status,
  ])
)

// https://vite.dev/config/
export default defineConfig({
  // The built SPA is served by Laravel from /app/ (backend/public/app)
  base: '/app/',
  define: { __LOCALE_STATUS__: JSON.stringify(localeStatus) },
  build: {
    outDir: '../backend/public/app',
    // Read by scripts/bundle-size.mjs (CI budget).
    manifest: true,
    emptyOutDir: true,
  },
  plugins: [
    tanstackRouter({
      target: 'react',
      autoCodeSplitting: true,
    }),
    react(),
    tailwindcss(),
  ],
  // Bundle these up front: the browser tests reach them only through the
  // translation-key scan (all source files), so Vite would otherwise find
  // them mid-run and reload the page, failing a random test.
  optimizeDeps: {
    include: [
      'react-dom/client',
      '@tanstack/react-query-devtools',
      '@tanstack/react-router-devtools',
      'react-top-loading-bar',
    ],
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      // The guest page's own scripts (Blade-inlined), for their unit tests.
      '@public-menu': path.resolve(
        __dirname,
        '../backend/resources/public-menu'
      ),
      '@guest-public': path.resolve(__dirname, '../backend/public'),
    },
  },
  server: {
    fs: {
      allow: [
        path.resolve(__dirname, '.'),
        path.resolve(__dirname, '../backend/resources/public-menu'),
        path.resolve(__dirname, '../backend/public/sw.js'),
      ],
    },
  },
  test: {
    setupFiles: ['./src/test-setup.ts'],
    silent: 'passed-only',
    unstubEnvs: true,
    browser: {
      enabled: true,
      provider: playwright(),
      instances: [{ browser: 'chromium' }],
    },
    coverage: {
      // include: ['src/**/*.{js,jsx,ts,tsx}'], // Uncomment to expand the report to all src/**/* so untested modules appear as 0% coverage.
      exclude: [
        'src/components/ui/**',
        'src/assets/**',
        'src/tanstack-table.d.ts',
        'src/routeTree.gen.ts',
        'src/test-utils/**',
        'src/routes/**',
      ],
    },
  },
})
