import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // The formatter uses DOM APIs (DOMPurify, TreeWalker), so it needs a DOM.
    environment: 'jsdom',
    include: ['resources/js/**/*.test.js'],
  },
});
