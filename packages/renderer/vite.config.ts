import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// The renderer ships prebuilt inside the PHP package, so applications need no Node.js.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  build: {
    outDir: fileURLToPath(new URL('../core/resources/dist', import.meta.url)),
    emptyOutDir: true,
    cssCodeSplit: false,
    sourcemap: true,
    // PHP finds the hashed entry in .vite/manifest.json. File names change with content, so they are
    // cached forever, and every chunk imports the entry by the same URL the page loaded it with.
    manifest: true,
    rollupOptions: {
      input: fileURLToPath(new URL('./src/main.tsx', import.meta.url)),
      output: {
        entryFileNames: 'vanda-[hash].js',
        chunkFileNames: 'vanda-[name]-[hash].js',
        assetFileNames: (asset) => (asset.names?.[0]?.endsWith('.css') ? 'vanda-[hash].css' : 'vanda-[name]-[hash][extname]'),
      },
    },
  },
  test: {
    environment: 'node',
  },
});
