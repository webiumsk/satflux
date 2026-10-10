export function buildPosEmbedForm(appUrl: string, buyLabel: string): string {
  if (!appUrl) return '';
  const escape = (value: string) => value.replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[char]!);
  return `<form method="POST" action="${escape(appUrl)}">
  <input type="hidden" name="email" value="customer@example.com" />
  <input type="hidden" name="orderId" value="CustomOrderId" />
  <input type="hidden" name="redirectUrl" value="https://example.com/thankyou" />
  <button type="submit" name="choiceKey" value="produkt">${escape(buyLabel)}</button>
</form>`;
}
