import { describe, expect, it } from 'vitest';
import { buildPosEmbedForm } from '../utils/posEmbed';

describe('BTCPay POS form compatibility', () => {
  it('posts supported fields and leaves notification settings to the app', () => {
    const container = document.createElement('div');
    container.innerHTML = buildPosEmbedForm('https://btcpay.example/apps/pos', 'Buy <now>');
    const form = container.querySelector('form')!;
    expect(form.action).toBe('https://btcpay.example/apps/pos');
    expect(form.method).toBe('post');
    expect([...form.querySelectorAll('[name]')].map((input) => input.getAttribute('name')))
      .toEqual(['email', 'orderId', 'redirectUrl', 'choiceKey']);
    expect(form.querySelector('button')!.textContent).toBe('Buy <now>');
  });

  it('waits for the configured app URL', () => {
    expect(buildPosEmbedForm('', 'Buy')).toBe('');
  });
});
