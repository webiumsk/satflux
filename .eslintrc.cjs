/* eslint-env node */
/* global module */
/** @type {import('eslint').Linter.Config} */
module.exports = {
  root: true,
  env: {
    browser: true,
    es2022: true,
    node: true,
  },
  extends: [
    "plugin:vue/vue3-essential",
    "eslint:recommended",
    "@vue/eslint-config-typescript",
  ],
  ignorePatterns: [
    "node_modules",
    "public/**",
    "public/build",
    "vendor",
    "bootstrap/ssr",
    "storage",
    "database",
    "tests",
    "scripts",
  ],
  rules: {
    "no-useless-catch": "off",
    "no-empty": "warn",
    "no-inner-declarations": "off",
    "vue/multi-word-component-names": "off",
    // Pragmatic defaults for an existing app; tighten over time
    "vue/no-mutating-props": "off",
    "vue/no-use-v-if-with-v-for": "off",
    "vue/require-toggle-inside-transition": "off",
    "vue/no-dupe-keys": "warn",
    "vue/no-unused-vars": "off",
    "@typescript-eslint/no-unused-vars": "warn",
  },
};
