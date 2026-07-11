/**
 * vue-i18n JIT compilation flag - MUST be the first import of every entry that
 * uses vue-i18n, before any module that (transitively) imports it.
 *
 * JIT compiles messages to an AST interpreted at runtime instead of
 * `new Function(...)`, which the CSP blocks (script-src has no 'unsafe-eval').
 * The Vite `define` covers the production build; this runtime global covers
 * the dev server, where pre-bundled dependencies do not see `define`
 * replacements. vue-i18n's feature-flag guard keeps a pre-set boolean.
 */
(globalThis as Record<string, unknown>).__INTLIFY_JIT_COMPILATION__ = true;

export {};
