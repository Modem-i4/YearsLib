import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath } from 'node:url'

const projectRoot = fileURLToPath(new URL('.', import.meta.url))

export default defineConfig({
  root: projectRoot,
  publicDir: false,
  plugins: [vue(), tailwindcss()],
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: `${projectRoot}/src/main.js`,
      output: {
        entryFileNames: 'assets/library.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: (asset) => asset.name?.endsWith('.css')
          ? 'assets/library.css'
          : 'assets/[name][extname]',
      },
    },
  },
})