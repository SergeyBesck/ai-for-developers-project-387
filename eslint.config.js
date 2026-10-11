import js from '@eslint/js';
import globals from 'globals';

export default [
  { ignores: ['public/assets/sdk.gen.js'] },
  js.configs.recommended,
  {
    files: ['public/assets/**/*.js'],
    languageOptions: { globals: globals.browser, sourceType: 'module' },
  },
  {
    files: ['tests/js/**/*.mjs'],
    languageOptions: { globals: globals.node, sourceType: 'module' },
  },
];
