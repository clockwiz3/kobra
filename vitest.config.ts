import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

/**
 * Конфиг для компонентных/unit-тестов (Vitest + @vue/test-utils).
 * Отдельный от vite.config.ts: тут не нужны laravel/tailwind/PWA-плагины —
 * только Vue SFC-компиляция + алиас '@' (зеркалит tsconfig paths) + jsdom.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/test/setup.ts'],
    include: ['resources/js/**/*.{test,spec}.ts'],
  },
})
