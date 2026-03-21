import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: 'assets/dist',
    assetsInlineLimit: 0,
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app:    resolve(__dirname, 'assets/src/main.jsx'),
        widget: resolve(__dirname, 'assets/src/widget/main.jsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: (info) => (info.name?.endsWith('.css') ? '[name].css' : '[name][extname]'),
      },
    },
  },
});
