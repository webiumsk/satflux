/**
 * Whether Pay Button "generated code" is real embed HTML, not a loading/error placeholder.
 * Real snippets from PayButtonForm start with `<style>`; placeholders use HTML comments
 * (`<!-- Loading... -->`, `<!-- Error: ... -->`, `<!-- BTCPay base URL unavailable... -->`).
 * We avoid matching the word "error" in user-facing fields inside real HTML.
 */
export function isPayButtonEmbedCodeCopyable(raw: string | undefined): boolean {
  const s = raw?.trim() ?? "";
  if (!s) return false;
  if (s.startsWith("<!--")) return false;
  if (/\bloading\s+store\s+information\b/i.test(s)) return false;
  return true;
}
