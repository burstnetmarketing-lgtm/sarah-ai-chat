import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  base: './',
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/src'),
    },
  },
  build: {
    outDir: 'assets/dist',
    assetsInlineLimit: 0,
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'assets/src/main.jsx'),
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: (info) => (info.name?.endsWith('.css') ? 'app.css' : '[name][extname]'),
      },
    },
  },
});
