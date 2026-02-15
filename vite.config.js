import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@css': path.resolve(__dirname, 'src/AppBundle/Resources/public/css'),
      '@components': path.resolve(__dirname, 'src/AppBundle/Resources/ReactViews/card/components'),
      '@utils': path.resolve(__dirname, 'src/AppBundle/Resources/ReactViews/card/utils'),
    },
  },
  build: {
    outDir: 'web/react',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        card: path.resolve(__dirname, 'src/AppBundle/Resources/ReactViews/card/index.jsx'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/[name][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
  css: {
    postcss: './postcss.config.js',
  },
});
