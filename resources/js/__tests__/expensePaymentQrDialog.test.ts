import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import ExpensePaymentQrDialog from '@/components/invoicing/ExpensePaymentQrDialog.vue';

const mocks = vi.hoisted(() => ({ image: vi.fn(), payme: vi.fn(), square: vi.fn() }));
vi.mock('qrcode', () => ({ default: { toDataURL: mocks.image } }));
vi.mock('@/utils/expensePaymentQr', () => ({
  expensePayMePayload: mocks.payme,
  expensePayBySquarePayload: mocks.square,
}));

const details = { iban: 'SK3112000000198742637541', variableSymbol: '000123', amount: '12.34', currency: 'EUR' as const, supplier: 'Synthetic supplier' };
const image = 'data:image/png;base64,c3ludGhldGlj';
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
const messages = { invoicing: {
  expense_payment_qr_title: 'Expense payment QR', expense_payment_qr_format: 'QR format',
  expense_payment_qr_recipient: 'Recipient', expense_payment_qr_iban: 'IBAN',
  expense_payment_qr_amount: 'Amount', expense_payment_qr_vs: 'Variable symbol',
  expense_payment_qr_verify: 'Verify payment details in your bank. Scanning does not mark the expense as paid.',
  expense_payment_qr_loading: 'Preparing QR', expense_payment_qr_error: 'QR could not be prepared.',
  expense_payment_qr_retry: 'Try again', expense_payment_qr_open_payme: 'Open PayMe',
  expense_payment_qr_image_alt: '{format} payment QR',
}, common: { close: 'Close' } };
let wrapper: VueWrapper | undefined;

function render(paymentDetails: typeof details | null = details) {
  wrapper = mount(ExpensePaymentQrDialog, {
    props: { details: paymentDetails }, attachTo: document.body,
    global: { plugins: [createI18n({ legacy: false, locale: 'en', messages: { en: messages } })] },
  });
  return wrapper;
}

const dom = () => new DOMWrapper(document.querySelector('dialog')!);

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

beforeEach(() => {
  Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: vi.fn(function (this: HTMLDialogElement) { this.setAttribute('open', ''); }) });
  Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: vi.fn(function (this: HTMLDialogElement) { this.removeAttribute('open'); }) });
  mocks.image.mockReset().mockResolvedValue(image);
  mocks.square.mockReset().mockReturnValue('SYNTHETIC-SQUARE');
  mocks.payme.mockReset().mockReturnValue('https://payme.sk/?V=2&IBAN=synthetic&AM=12.34');
});

afterEach(() => {
  wrapper?.unmount(); wrapper = undefined;
  document.body.innerHTML = '';
  if (originalShowModal) Object.defineProperty(HTMLDialogElement.prototype, 'showModal', originalShowModal);
  else Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal');
  if (originalClose) Object.defineProperty(HTMLDialogElement.prototype, 'close', originalClose);
  else Reflect.deleteProperty(HTMLDialogElement.prototype, 'close');
  vi.restoreAllMocks();
});

describe('expense payment QR dialog', () => {
  it('stays closed and generates nothing without eligible payment details', async () => {
    render(null);
    await flushPromises();
    expect(dom().attributes('open')).toBeUndefined();
    expect(mocks.image).not.toHaveBeenCalled();
  });

  it('defaults to PAY by square with locally generated QR and visible exact payment details', async () => {
    render(); await flushPromises();
    expect(dom().attributes('open')).toBe('');
    expect(dom().get('button[aria-pressed="true"]').text()).toBe('PAY by square');
    expect(mocks.square).toHaveBeenCalledWith(details);
    expect(mocks.image).toHaveBeenCalledWith([{ data: 'SYNTHETIC-SQUARE', mode: 'alphanumeric' }], expect.objectContaining({ margin: 4, errorCorrectionLevel: 'L' }));
    expect(dom().get('img').attributes('src')).toBe(image);
    for (const value of ['Synthetic supplier', details.iban, '000123', '12.34 EUR']) expect(dom().text()).toContain(value);
    expect(dom().find('a').exists()).toBe(false);
    expect(wrapper!.emitted()).not.toHaveProperty('mark-paid');
  });

  it('clears the previous image and ignores an older encoding result after changing format', async () => {
    const first = deferred<string>(); const second = deferred<string>();
    mocks.image.mockReset().mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    render(); await flushPromises();
    await dom().findAll('button').find(button => button.text() === 'PayMe')!.trigger('click');
    await flushPromises();
    expect(dom().find('img').exists()).toBe(false);
    expect(dom().find('a').exists()).toBe(false);
    second.resolve(`${image}2`); await flushPromises();
    expect(dom().get('img').attributes('src')).toBe(`${image}2`);
    first.resolve(`${image}1`); await flushPromises();
    expect(dom().get('img').attributes('src')).toBe(`${image}2`);
    const link = dom().get('a');
    expect(link.attributes('href')).toBe(mocks.payme.mock.results[0].value);
    expect(link.attributes('rel')).toContain('noreferrer');
    expect(link.attributes('referrerpolicy')).toBe('no-referrer');
  });

  it('drops the existing image when payment data changes and discards a pending stale result', async () => {
    render(); await flushPromises();
    const pending = deferred<string>(); mocks.image.mockReturnValueOnce(pending.promise);
    await wrapper!.setProps({ details: { ...details, amount: '25.00' } });
    expect(dom().find('img').exists()).toBe(false);
    expect(dom().text()).toContain('25.00 EUR');
    await wrapper!.setProps({ details: null });
    pending.resolve(`${image}old`); await flushPromises();
    expect(dom().attributes('open')).toBeUndefined();
    expect(dom().find('img').exists()).toBe(false);
  });

  it('shows a fixed recoverable error and does not reveal exception data', async () => {
    mocks.image.mockRejectedValueOnce(new Error('private supplier payload'));
    render(); await flushPromises();
    expect(dom().get('[role="alert"]').text()).toBe('QR could not be prepared.');
    expect(dom().text()).not.toContain('private supplier payload');
    await dom().findAll('button').find(button => button.text() === 'Try again')!.trigger('click');
    await flushPromises();
    expect(dom().find('[role="alert"]').exists()).toBe(false);
    expect(dom().get('img').attributes('src')).toBe(image);
  });

  it('requests closing on Escape/cancel and restores focus when closed', async () => {
    const opener = document.createElement('button'); document.body.append(opener); opener.focus();
    render(); await flushPromises();
    expect(document.activeElement?.textContent).toBe('Close');
    await dom().trigger('cancel');
    expect(wrapper!.emitted('close')).toHaveLength(1);
    await wrapper!.setProps({ details: null });
    expect(document.activeElement).toBe(opener);
  });

  it('renders supplier content as text and closes without changing payment status', async () => {
    render({ ...details, supplier: '<img src=x onerror=alert(1)>' }); await flushPromises();
    expect(dom().text()).toContain('<img src=x onerror=alert(1)>');
    expect(dom().findAll('img')).toHaveLength(1);
    await dom().findAll('button').find(button => button.text() === 'Close')!.trigger('click');
    expect(Object.keys(wrapper!.emitted())).toEqual(expect.arrayContaining(['close']));
    expect(wrapper!.emitted()).not.toHaveProperty('mark-paid');
  });
});
