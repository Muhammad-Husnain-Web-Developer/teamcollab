import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // The formatter uses DOM APIs (DOMPurify, TreeWalker), so it needs a DOM.
    environment: 'jsdom',
    include: ['resources/js/**/*.test.js'],
    // The first test in each store file pays for a dynamic import of the
    // store; on a loaded machine that alone can pass the 5s default.
    testTimeout: 15000,
  },
});
